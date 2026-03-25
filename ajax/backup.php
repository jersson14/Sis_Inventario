<?php
if (strlen(session_id()) < 1) {
    session_start();
}

require_once "../modelos/Backup.php";

if (!isset($_SESSION['acceso']) || (int)$_SESSION['acceso'] !== 1) {
    if (isset($_GET['op']) && $_GET['op'] === 'descargar') {
        http_response_code(403);
        exit;
    }
    echo 'No tienes permiso para este modulo';
    exit;
}

$backup = new Backup();
$idusuario = $_SESSION['idusuario'];

switch ($_GET['op']) {
    case 'generar':
        $res = $backup->generar($idusuario);
        echo json_encode($res);
        break;

    case 'listar':
        $rspta = $backup->listarLogs();
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $botonDesc = ($reg->tipo === 'BACKUP') ? '<a class="btn btn-primary btn-xs" href="../ajax/backup.php?op=descargar&archivo=' . urlencode($reg->archivo) . '"><i class="fa fa-download"></i></a>' : '-';
            $data[] = array(
                '0' => $botonDesc,
                '1' => $reg->tipo,
                '2' => $reg->archivo,
                '3' => round(((float)$reg->tamano_bytes) / 1024, 2) . ' KB',
                '4' => $reg->usuario,
                '5' => date('d/m/Y H:i', strtotime($reg->fecha_hora))
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'descargar':
        $archivo = isset($_GET['archivo']) ? basename($_GET['archivo']) : '';
        $ruta = "../files/backups/" . $archivo;

        if ($archivo === '' || !file_exists($ruta)) {
            http_response_code(404);
            echo 'Archivo no encontrado';
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;

    case 'restaurar':
        if (!isset($_FILES['archivo_sql']) || !file_exists($_FILES['archivo_sql']['tmp_name'])) {
            echo 'Selecciona un archivo SQL';
            break;
        }

        $res = $backup->restaurar($_FILES['archivo_sql']['tmp_name'], $idusuario);
        echo $res['ok'] ? $res['message'] : $res['message'];
        break;
}
?>
