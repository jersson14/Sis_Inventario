<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('acceso'));
require_once "../modelos/Auditoria.php";

$auditoria = new Auditoria();
$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {
	case 'listar':
		$fi = fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', '');
		$ff = fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', '');
		$modulo = isset($_GET['modulo']) ? substr(preg_replace('/[^a-z_]/', '', strtolower($_GET['modulo'])), 0, 40) : '';
		$idusuario = enteroSeguro(isset($_GET['idusuario']) ? $_GET['idusuario'] : 0);
		$rs = $auditoria->listar($fi, $ff, $modulo, $idusuario);
		$data = array();
		$colores = array('crear' => 'bg-green', 'editar' => 'bg-aqua', 'anular' => 'bg-red', 'desactivar' => 'bg-yellow', 'activar' => 'bg-green', 'login' => 'bg-purple', 'logout' => 'bg-gray', 'eliminar' => 'bg-red');
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				$clase = 'bg-gray';
				foreach ($colores as $k => $c) {
					if (strpos($reg->accion, $k) === 0) { $clase = $c; break; }
				}
				$data[] = array(
					'0' => date('d/m/Y H:i:s', strtotime($reg->fecha_hora)),
					'1' => e($reg->nombre_usuario) . ' <small class="text-soft">' . e($reg->usuario) . '</small>',
					'2' => '<span class="chip">' . e($reg->modulo) . '</span>',
					'3' => '<span class="label ' . $clase . '">' . e($reg->accion) . '</span>',
					'4' => e($reg->detalle),
					'5' => e($reg->ip)
				);
			}
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	case 'filtros':
		responderJson(array(
			'modulos' => $auditoria->modulos(),
			'usuarios' => $auditoria->usuarios()
		));
		break;

	case 'intentos':
		$rs = $auditoria->intentosLogin(100);
		$data = array();
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				$data[] = array(
					'0' => date('d/m/Y H:i:s', strtotime($reg->fecha_hora)),
					'1' => e($reg->login),
					'2' => e($reg->ip),
					'3' => (int)$reg->exito === 1 ? '<span class="label bg-green">Exitoso</span>' : '<span class="label bg-red">Fallido</span>'
				);
			}
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
