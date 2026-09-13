<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('backup', 'acceso'));
require_once "../modelos/Backup.php";

$backup = new Backup();
$idusuario = (int)$_SESSION['idusuario'];
$esAdmin = usuarioTienePermiso('acceso');

$op = isset($_GET['op']) ? (string)$_GET['op'] : '';

switch ($op) {
    case 'generar':
        $res = $backup->generar($idusuario);
        if (!empty($res['ok'])) {
            registrarAuditoria('backup', 'generar', 'Backup ' . $res['filename'] . ' (' . (int)$res['size'] . ' bytes)');
        }
        responderJson($res);
        break;

    case 'listar':
        $rspta = $backup->listarLogs();
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $btn = '-';
                if ($reg->tipo === 'BACKUP') {
                    $existe = $backup->rutaArchivo($reg->archivo) !== '';
                    if ($existe) {
                        $btn = '<a class="btn btn-primary btn-xs" title="Descargar" href="../ajax/backup.php?op=descargar&archivo=' . urlencode($reg->archivo) . '"><i class="fa fa-download"></i></a>';
                        if ($esAdmin) {
                            $btn .= ' <button class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminarBackup(\'' . e($reg->archivo) . '\')"><i class="fa fa-trash"></i></button>';
                        }
                    } else {
                        $btn = '<span class="label bg-gray" title="El archivo ya no existe en el servidor">NO DISPONIBLE</span>';
                    }
                }
                $data[] = array(
                    '0' => $btn,
                    '1' => e($reg->tipo),
                    '2' => e($reg->archivo),
                    '3' => round(((float)$reg->tamano_bytes) / 1024, 2) . ' KB',
                    '4' => e($reg->usuario),
                    '5' => date('d/m/Y H:i', strtotime($reg->fecha_hora))
                );
            }
        }
        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ), JSON_UNESCAPED_UNICODE);
        break;

    case 'descargar':
        $archivo = nombreArchivoSeguro(isset($_GET['archivo']) ? $_GET['archivo'] : '');
        $ruta = $backup->rutaArchivo($archivo);
        if ($ruta === '') {
            http_response_code(404);
            echo 'Archivo no encontrado';
            exit;
        }
        registrarAuditoria('backup', 'descargar', 'Descarga ' . $archivo);
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-store');
        readfile($ruta);
        exit;

    case 'restaurar':
        if (!$esAdmin) {
            responderJson(array('ok' => false, 'message' => 'Solo un administrador puede restaurar la base de datos.'), 403);
        }
        if (!isset($_FILES['archivo_sql']) || !is_array($_FILES['archivo_sql']) || (int)$_FILES['archivo_sql']['error'] === UPLOAD_ERR_NO_FILE) {
            responderJson(array('ok' => false, 'message' => 'Selecciona un archivo SQL'));
        }
        $f = $_FILES['archivo_sql'];
        if ((int)$f['error'] !== UPLOAD_ERR_OK) {
            responderJson(array('ok' => false, 'message' => 'Error al subir el archivo (codigo ' . (int)$f['error'] . ').'));
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            responderJson(array('ok' => false, 'message' => 'Archivo no valido.'));
        }
        $nombreOriginal = basename((string)$f['name']);
        if (strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'sql') {
            responderJson(array('ok' => false, 'message' => 'Solo se permiten archivos con extension .sql'));
        }
        if ((int)$f['size'] > Backup::MAX_BYTES_RESTAURAR) {
            responderJson(array('ok' => false, 'message' => 'El archivo supera el tamano maximo (64 MB).'));
        }

        set_time_limit(600);
        $res = $backup->restaurar($f['tmp_name'], $idusuario, $nombreOriginal);
        registrarAuditoria('backup', !empty($res['ok']) ? 'restaurar' : 'restaurar_error', 'Archivo ' . $nombreOriginal . ' previo ' . (isset($res['backup_previo']) ? $res['backup_previo'] : '-'));
        responderJson(array(
            'ok' => !empty($res['ok']),
            'message' => $res['message'],
            'backup_previo' => isset($res['backup_previo']) ? $res['backup_previo'] : ''
        ));
        break;

    case 'eliminar':
        if (!$esAdmin) {
            echo 'Solo un administrador puede eliminar backups.';
            break;
        }
        $archivo = nombreArchivoSeguro(isset($_POST['archivo']) ? $_POST['archivo'] : '');
        $res = $backup->eliminar($archivo, $idusuario);
        if (!empty($res['ok'])) {
            registrarAuditoria('backup', 'eliminar', 'Backup ' . $archivo . ' eliminado');
        }
        echo $res['message'];
        break;

    default:
        responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
        break;
}
