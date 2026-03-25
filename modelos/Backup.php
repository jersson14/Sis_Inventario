<?php
require "../config/Conexion.php";

class Backup
{
    public function __construct()
    {
    }

    public function generar($idusuario)
    {
        global $conexion;

        $dir = "../files/backups";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = "backup_" . date('Ymd_His') . ".sql";
        $filepath = $dir . "/" . $filename;

        $output = "-- Backup generado por el sistema\n";
        $output .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Base de datos: " . DB_NAME . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = array();
        $rsTables = $conexion->query("SHOW TABLES");
        while ($row = $rsTables->fetch_array()) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            $output .= "-- ----------------------------\n";
            $output .= "-- Tabla: `" . $table . "`\n";
            $output .= "-- ----------------------------\n";

            $resCreate = $conexion->query("SHOW CREATE TABLE `" . $table . "`");
            $rowCreate = $resCreate->fetch_assoc();
            $createSql = isset($rowCreate['Create Table']) ? $rowCreate['Create Table'] : '';

            $output .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
            $output .= $createSql . ";\n\n";

            $resData = $conexion->query("SELECT * FROM `" . $table . "`");
            while ($row = $resData->fetch_assoc()) {
                $cols = array();
                $vals = array();
                foreach ($row as $col => $val) {
                    $cols[] = "`" . $col . "`";
                    if (is_null($val)) {
                        $vals[] = "NULL";
                    } else {
                        $vals[] = "'" . mysqli_real_escape_string($conexion, $val) . "'";
                    }
                }
                $output .= "INSERT INTO `" . $table . "` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            $output .= "\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($filepath, $output);

        $size = file_exists($filepath) ? filesize($filepath) : 0;
        $sqlLog = "INSERT INTO backup_log(idusuario,archivo,tamano_bytes,tipo) VALUES('$idusuario','$filename','$size','BACKUP')";
        ejecutarConsulta($sqlLog);

        return array(
            'ok' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $size
        );
    }

    public function restaurar($tmpFile, $idusuario)
    {
        global $conexion;

        if (!file_exists($tmpFile)) {
            return array('ok' => false, 'message' => 'Archivo no encontrado');
        }

        $sqlContent = file_get_contents($tmpFile);
        if ($sqlContent === false || trim($sqlContent) === '') {
            return array('ok' => false, 'message' => 'Archivo SQL vacio');
        }

        $queries = array();
        $buffer = '';
        $lines = preg_split('/\r\n|\r|\n/', $sqlContent);

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '/*') === 0 || strpos($trim, '*/') === 0) {
                continue;
            }
            $buffer .= $line . "\n";
            if (substr(trim($line), -1) === ';') {
                $queries[] = $buffer;
                $buffer = '';
            }
        }

        if (!empty($buffer)) {
            $queries[] = $buffer;
        }

        foreach ($queries as $query) {
            if (!$conexion->query($query)) {
                return array('ok' => false, 'message' => 'Error SQL durante restauracion: ' . $conexion->error);
            }
        }

        $sqlLog = "INSERT INTO backup_log(idusuario,archivo,tamano_bytes,tipo) VALUES('$idusuario','restauracion_manual.sql','0','RESTORE')";
        ejecutarConsulta($sqlLog);

        return array('ok' => true, 'message' => 'Base de datos restaurada correctamente');
    }

    public function listarLogs()
    {
        $sql = "SELECT b.idbackup,b.archivo,b.tamano_bytes,b.fecha_hora,b.tipo,u.nombre AS usuario
        FROM backup_log b
        INNER JOIN usuario u ON u.idusuario=b.idusuario
        ORDER BY b.idbackup DESC";
        return ejecutarConsulta($sql);
    }
}
?>
