<?php
/**
 * Modelo de copias de seguridad (volcado SQL a files/backups y restauracion).
 *
 * El volcado usa db()->real_escape_string porque genera texto SQL, no
 * ejecuta consultas con datos externos. El log (backup_log) usa preparadas.
 */
require_once "../config/Conexion.php";

class Backup
{
    const DIR = "../files/backups";
    const MAX_BYTES_RESTAURAR = 67108864; // 64 MB
    const CHUNK_BYTES = 1048576;          // 1 MB por lote de multi_query

    public function __construct()
    {
    }

    /** Ruta absoluta de la carpeta de backups (la crea si no existe). */
    public function directorio()
    {
        if (!is_dir(self::DIR)) {
            @mkdir(self::DIR, 0775, true);
        }
        return self::DIR;
    }

    /** Registra una fila en backup_log. */
    private function registrarLog($idusuario, $archivo, $bytes, $tipo)
    {
        return dbExec(
            "INSERT INTO backup_log(idusuario, archivo, tamano_bytes, tipo) VALUES(?,?,?,?)",
            array((int)$idusuario, substr((string)$archivo, 0, 180), (int)$bytes, substr((string)$tipo, 0, 20))
        );
    }

    /**
     * Genera un volcado completo de la BD. $prefijo permite distinguir los
     * backups automaticos previos a una restauracion.
     * Devuelve array(ok, filename, filepath, size) o array(ok=false, message).
     */
    public function generar($idusuario, $prefijo = 'backup')
    {
        $cx = db();
        $dir = $this->directorio();
        if (!is_dir($dir) || !is_writable($dir)) {
            return array('ok' => false, 'message' => 'La carpeta de backups no existe o no tiene permisos de escritura.');
        }

        $prefijo = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$prefijo);
        if ($prefijo === '') {
            $prefijo = 'backup';
        }
        $base = $prefijo . "_" . date('Ymd_His');
        $filename = $base . ".sql";
        $filepath = $dir . "/" . $filename;
        // Evitar colision si se generan dos backups en el mismo segundo
        for ($n = 2; file_exists($filepath) && $n < 100; $n++) {
            $filename = $base . "_" . $n . ".sql";
            $filepath = $dir . "/" . $filename;
        }

        $fh = @fopen($filepath, 'wb');
        if (!$fh) {
            return array('ok' => false, 'message' => 'No se pudo crear el archivo de backup.');
        }

        try {
            fwrite($fh, "-- Backup generado por el sistema\n");
            fwrite($fh, "-- Fecha: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "-- Base de datos: " . DB_NAME . "\n\n");
            fwrite($fh, "SET NAMES utf8mb4;\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = array();
            $rsTables = $cx->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
            while ($row = $rsTables->fetch_array()) {
                $tables[] = $row[0];
            }
            $rsTables->free();

            foreach ($tables as $table) {
                $tq = "`" . str_replace("`", "``", $table) . "`";
                fwrite($fh, "-- ----------------------------\n");
                fwrite($fh, "-- Tabla: " . $tq . "\n");
                fwrite($fh, "-- ----------------------------\n");

                $resCreate = $cx->query("SHOW CREATE TABLE " . $tq);
                $rowCreate = $resCreate->fetch_assoc();
                $resCreate->free();
                $createSql = isset($rowCreate['Create Table']) ? $rowCreate['Create Table'] : '';

                fwrite($fh, "DROP TABLE IF EXISTS " . $tq . ";\n");
                fwrite($fh, $createSql . ";\n\n");

                $resData = $cx->query("SELECT * FROM " . $tq, MYSQLI_USE_RESULT);
                $cols = null;
                while ($row = $resData->fetch_assoc()) {
                    if ($cols === null) {
                        $cols = array();
                        foreach (array_keys($row) as $col) {
                            $cols[] = "`" . str_replace("`", "``", $col) . "`";
                        }
                        $cols = implode(',', $cols);
                    }
                    $vals = array();
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = "NULL";
                        } else {
                            $vals[] = "'" . $cx->real_escape_string((string)$val) . "'";
                        }
                    }
                    fwrite($fh, "INSERT INTO " . $tq . " (" . $cols . ") VALUES (" . implode(',', $vals) . ");\n");
                }
                $resData->free();
                fwrite($fh, "\n");
            }

            // Triggers
            $rsTrg = $cx->query("SHOW TRIGGERS");
            $triggers = array();
            while ($t = $rsTrg->fetch_assoc()) {
                $triggers[] = $t['Trigger'];
            }
            $rsTrg->free();
            if (count($triggers) > 0) {
                fwrite($fh, "-- ----------------------------\n-- Triggers\n-- ----------------------------\n");
                foreach ($triggers as $trg) {
                    $tq = "`" . str_replace("`", "``", $trg) . "`";
                    $rc = $cx->query("SHOW CREATE TRIGGER " . $tq);
                    $rowT = $rc->fetch_assoc();
                    $rc->free();
                    if (isset($rowT['SQL Original Statement'])) {
                        fwrite($fh, "DROP TRIGGER IF EXISTS " . $tq . ";\n");
                        fwrite($fh, $rowT['SQL Original Statement'] . ";\n\n");
                    }
                }
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $e) {
            fclose($fh);
            @unlink($filepath);
            appLog('error', 'Backup fallido: ' . $e->getMessage());
            return array('ok' => false, 'message' => 'No se pudo generar el backup.');
        }
        fclose($fh);

        $size = file_exists($filepath) ? filesize($filepath) : 0;
        $this->registrarLog($idusuario, $filename, $size, 'BACKUP');

        return array(
            'ok' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $size
        );
    }

    /**
     * Restaura la BD desde un archivo .sql subido. Antes genera un backup de
     * seguridad. Ejecuta las sentencias en lotes con multi_query.
     * Devuelve array(ok, message, backup_previo).
     */
    public function restaurar($tmpFile, $idusuario, $nombreOriginal = '')
    {
        $cx = db();

        if (!is_file($tmpFile)) {
            return array('ok' => false, 'message' => 'Archivo no encontrado');
        }
        $bytes = filesize($tmpFile);
        if ($bytes <= 0) {
            return array('ok' => false, 'message' => 'Archivo SQL vacio');
        }
        if ($bytes > self::MAX_BYTES_RESTAURAR) {
            return array('ok' => false, 'message' => 'El archivo supera el tamano maximo (64 MB).');
        }

        $sqlContent = file_get_contents($tmpFile);
        if ($sqlContent === false || trim($sqlContent) === '') {
            return array('ok' => false, 'message' => 'Archivo SQL vacio');
        }
        if (stripos($sqlContent, 'CREATE TABLE') === false && stripos($sqlContent, 'INSERT INTO') === false) {
            return array('ok' => false, 'message' => 'El archivo no parece un volcado SQL valido (sin CREATE TABLE ni INSERT INTO).');
        }

        // Backup de seguridad antes de tocar nada
        $previo = $this->generar($idusuario, 'pre_restore');
        if (empty($previo['ok'])) {
            return array('ok' => false, 'message' => 'No se pudo generar el backup de seguridad previo; restauracion cancelada.');
        }

        // Separar en sentencias (ignorando lineas de comentario) y agrupar en lotes
        $lotes = array();
        $buffer = '';
        $lote = '';
        $lines = preg_split('/\r\n|\r|\n/', $sqlContent);
        unset($sqlContent);
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '#') === 0 || strpos($trim, '/*') === 0 || strpos($trim, '*/') === 0) {
                continue;
            }
            $buffer .= $line . "\n";
            if (substr($trim, -1) === ';') {
                $lote .= $buffer;
                $buffer = '';
                if (strlen($lote) >= self::CHUNK_BYTES) {
                    $lotes[] = $lote;
                    $lote = '';
                }
            }
        }
        if (trim($buffer) !== '') {
            $lote .= rtrim($buffer) . ";\n";
        }
        if (trim($lote) !== '') {
            $lotes[] = $lote;
        }
        unset($lines);

        $ejecutadas = 0;
        try {
            $cx->query("SET FOREIGN_KEY_CHECKS=0");
            foreach ($lotes as $sqlLote) {
                if (!$cx->multi_query($sqlLote)) {
                    throw new RuntimeException($cx->error);
                }
                // Consumir todos los resultados del lote
                do {
                    $r = $cx->store_result();
                    if ($r instanceof mysqli_result) {
                        $r->free();
                    }
                    $ejecutadas++;
                    if (!$cx->more_results()) {
                        break;
                    }
                } while ($cx->next_result());
                if ($cx->errno) {
                    throw new RuntimeException($cx->error);
                }
            }
            $cx->query("SET FOREIGN_KEY_CHECKS=1");
        } catch (Throwable $e) {
            // Limpiar resultados pendientes para no dejar la conexion inconsistente
            try {
                while ($cx->more_results() && $cx->next_result()) {
                    $r = $cx->store_result();
                    if ($r instanceof mysqli_result) {
                        $r->free();
                    }
                }
                $cx->query("SET FOREIGN_KEY_CHECKS=1");
            } catch (Throwable $e2) {
                // ignorar
            }
            appLog('error', 'Restauracion fallida: ' . $e->getMessage());
            return array(
                'ok' => false,
                'message' => 'Error SQL durante la restauracion: ' . $e->getMessage() . '. Se genero un backup previo: ' . $previo['filename'],
                'backup_previo' => $previo['filename']
            );
        }

        $nombreLog = nombreArchivoSeguro($nombreOriginal);
        if ($nombreLog === '') {
            $nombreLog = 'restauracion_manual.sql';
        }
        $this->registrarLog($idusuario, $nombreLog, $bytes, 'RESTORE');

        return array(
            'ok' => true,
            'message' => 'Base de datos restaurada correctamente (' . $ejecutadas . ' sentencias). Backup previo: ' . $previo['filename'],
            'backup_previo' => $previo['filename'],
            'sentencias' => $ejecutadas
        );
    }

    /** Ruta del archivo de backup si existe en la carpeta, o '' si no. */
    public function rutaArchivo($archivo)
    {
        $archivo = nombreArchivoSeguro($archivo);
        if ($archivo === '' || strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'sql') {
            return '';
        }
        $ruta = self::DIR . "/" . $archivo;
        return is_file($ruta) ? $ruta : '';
    }

    /** Elimina un backup del disco y lo marca en el log. Devuelve array(ok, message). */
    public function eliminar($archivo, $idusuario)
    {
        $archivo = nombreArchivoSeguro($archivo);
        $ruta = $this->rutaArchivo($archivo);
        if ($ruta === '') {
            return array('ok' => false, 'message' => 'El archivo de backup no existe.');
        }
        $bytes = filesize($ruta);
        if (!@unlink($ruta)) {
            return array('ok' => false, 'message' => 'No se pudo eliminar el archivo.');
        }
        $this->registrarLog($idusuario, $archivo, $bytes, 'ELIMINADO');
        return array('ok' => true, 'message' => 'Backup eliminado correctamente');
    }

    public function listarLogs()
    {
        $sql = "SELECT b.idbackup, b.archivo, b.tamano_bytes, b.fecha_hora, b.tipo, IFNULL(u.nombre,'-') AS usuario
        FROM backup_log b
        LEFT JOIN usuario u ON u.idusuario=b.idusuario
        ORDER BY b.idbackup DESC";
        return dbQuery($sql);
    }
}
