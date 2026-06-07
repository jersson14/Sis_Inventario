<?php 
require_once "global.php";

$conexion=new mysqli(DB_HOST,DB_USERNAME,DB_PASSWORD,DB_NAME);

mysqli_query($conexion, 'SET NAMES "'.DB_ENCODE.'"');

//muestra posible error en la conexion
if (mysqli_connect_errno()) {
	printf("Falló en la conexion con la base de datos: %s\n",mysqli_connect_error());
	exit();
}

if (!function_exists('ejecutarConsulta')) {
	Function ejecutarConsulta($sql){ 
global $conexion;
$query=$conexion->query($sql);
return $query;

	}

	function ejecutarConsultaSimpleFila($sql){
global $conexion;
$query=$conexion->query($sql);
$row=$query->fetch_assoc();
return $row;
	}
function ejecutarConsulta_retornarID($sql){
global $conexion;
$query=$conexion->query($sql);
return $conexion->insert_id;
}

function limpiarCadena($str){
global $conexion;
$str=mysqli_real_escape_string($conexion,trim($str));
return htmlspecialchars($str);
}

function obtenerMonedaEmpresaCodigo(){
global $conexion;
	static $cachedMoneda = null;
	if ($cachedMoneda !== null) {
		return $cachedMoneda;
	}

	$cachedMoneda = 'PEN';
	$tabla = $conexion->query("SHOW TABLES LIKE 'configuracion_empresa'");
	if ($tabla && $tabla->num_rows > 0) {
		$rs = $conexion->query("SELECT moneda FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
		if ($rs && ($row = $rs->fetch_assoc())) {
			$moneda = strtoupper(trim((string)$row['moneda']));
			if ($moneda !== '') {
				$cachedMoneda = $moneda;
			}
		}
	}

	return $cachedMoneda;
}

function obtenerSimboloMoneda($codigo = null){
	if ($codigo === null || trim((string)$codigo) === '') {
		$codigo = obtenerMonedaEmpresaCodigo();
	}
	$codigo = strtoupper(trim((string)$codigo));

	$map = array(
		'PEN' => 'S/',
		'USD' => '$',
		'EUR' => 'EUR',
		'MXN' => 'MX$',
		'COP' => 'COP$',
		'CLP' => 'CLP$',
		'ARS' => 'AR$',
		'BOB' => 'Bs'
	);

	return isset($map[$codigo]) ? $map[$codigo] : $codigo;
}

function obtenerNombreMonedaLetras($codigo = null){
	if ($codigo === null || trim((string)$codigo) === '') {
		$codigo = obtenerMonedaEmpresaCodigo();
	}
	$codigo = strtoupper(trim((string)$codigo));

	$map = array(
		'PEN' => 'SOLES',
		'USD' => 'DOLARES',
		'EUR' => 'EUROS',
		'MXN' => 'PESOS MEXICANOS',
		'COP' => 'PESOS COLOMBIANOS',
		'CLP' => 'PESOS CHILENOS',
		'ARS' => 'PESOS ARGENTINOS',
		'BOB' => 'BOLIVIANOS'
	);

	return isset($map[$codigo]) ? $map[$codigo] : 'MONEDA';
}

function formatearMoneda($monto, $codigo = null, $decimales = 2){
	$simbolo = obtenerSimboloMoneda($codigo);
	return $simbolo . ' ' . number_format((float)$monto, (int)$decimales, '.', ',');
}

}

 ?>
