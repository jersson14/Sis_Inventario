<?php
require_once "../config/seguridad.php";
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST
requierePermiso(array('acceso'));
require_once "../modelos/Permiso.php";

$permiso = new Permiso();

$op = isset($_GET["op"]) ? $_GET["op"] : '';

switch ($op) {
	case 'listar':
		$rspta = $permiso->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$data[] = array(
					"0" => e($reg->nombre)
				);
			}
		}
		$results = array(
			"sEcho" => 1,
			"iTotalRecords" => count($data),
			"iTotalDisplayRecords" => count($data),
			"aaData" => $data
		);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		break;
}
