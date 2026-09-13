<?php
/**
 * Capa de acceso a datos (mysqli + consultas preparadas).
 *
 * Reglas:
 *  - Toda consulta con datos externos DEBE usar dbQuery/dbRow/dbAll/dbExec/dbInsert
 *    con parametros (?) en vez de interpolar variables en el SQL.
 *  - Las funciones legacy (ejecutarConsulta, ejecutarConsultaSimpleFila,
 *    ejecutarConsulta_retornarID) siguen existiendo para consultas SIN datos externos.
 *  - Los errores SQL no rompen la respuesta: se registran en logs/app.log y
 *    la funcion devuelve false/null/0.
 */
require_once __DIR__ . "/global.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conexion) || !($conexion instanceof mysqli)) {
	try {
		$conexion = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, (DB_PORT > 0 ? (int)DB_PORT : null));
		$conexion->set_charset(DB_ENCODE);
	} catch (Throwable $e) {
		appLog('critical', 'Fallo de conexion a la base de datos: ' . $e->getMessage());
		http_response_code(500);
		if (APP_ENV === 'production') {
			echo 'No se pudo conectar con la base de datos. Contacte al administrador.';
		} else {
			echo 'Fallo en la conexion con la base de datos: ' . htmlspecialchars($e->getMessage());
		}
		exit;
	}
}

if (!function_exists('db')) {

	/** @return mysqli */
	function db() {
		global $conexion;
		return $conexion;
	}

	/**
	 * Deduce la cadena de tipos para bind_param a partir de los valores.
	 */
	function dbTipos(array $params) {
		$tipos = '';
		foreach ($params as $p) {
			if (is_int($p)) {
				$tipos .= 'i';
			} elseif (is_float($p)) {
				$tipos .= 'd';
			} else {
				$tipos .= 's';
			}
		}
		return $tipos;
	}

	/**
	 * Ejecuta una consulta. Con $params usa sentencia preparada.
	 * Devuelve mysqli_result para SELECT, true para INSERT/UPDATE/DELETE, false si falla.
	 */
	function dbQuery($sql, array $params = array()) {
		global $conexion;
		try {
			if (count($params) === 0) {
				$r = $conexion->query($sql);
				$GLOBALS["__dbAfectadas"] = (int)$conexion->affected_rows;
				return $r;
			}
			$stmt = $conexion->prepare($sql);
			$tipos = dbTipos($params);
			$stmt->bind_param($tipos, ...$params);
			$stmt->execute();
			$GLOBALS["__dbAfectadas"] = (int)$stmt->affected_rows;
			$result = $stmt->get_result();
			if ($result === false) {
				// Sentencia sin conjunto de resultados (INSERT/UPDATE/DELETE)
				$ok = ($stmt->errno === 0);
				$stmt->close();
				return $ok;
			}
			$stmt->close();
			return $result;
		} catch (Throwable $e) {
			appLog('error', 'SQL: ' . $e->getMessage(), array('sql' => preg_replace('/\s+/', ' ', $sql)));
			if (APP_ENV !== 'production' && php_sapi_name() === 'cli') {
				fwrite(STDERR, 'SQL ERROR: ' . $e->getMessage() . PHP_EOL);
			}
			return false;
		}
	}

	/** Devuelve la primera fila como array asociativo o null. */
	function dbRow($sql, array $params = array()) {
		$rs = dbQuery($sql, $params);
		if (!($rs instanceof mysqli_result)) {
			return null;
		}
		$row = $rs->fetch_assoc();
		$rs->free();
		return $row ? $row : null;
	}

	/** Devuelve todas las filas como array de arrays asociativos. */
	function dbAll($sql, array $params = array()) {
		$rs = dbQuery($sql, $params);
		if (!($rs instanceof mysqli_result)) {
			return array();
		}
		$rows = $rs->fetch_all(MYSQLI_ASSOC);
		$rs->free();
		return $rows;
	}

	/** Devuelve el primer valor de la primera fila (o $default). */
	function dbValue($sql, array $params = array(), $default = null) {
		$rs = dbQuery($sql, $params);
		if (!($rs instanceof mysqli_result)) {
			return $default;
		}
		$row = $rs->fetch_row();
		$rs->free();
		return ($row && isset($row[0])) ? $row[0] : $default;
	}

	/** Ejecuta INSERT/UPDATE/DELETE. Devuelve true/false. */
	function dbExec($sql, array $params = array()) {
		$r = dbQuery($sql, $params);
		return $r !== false;
	}

	/** Ejecuta INSERT y devuelve el id generado (0 si falla). */
	function dbInsert($sql, array $params = array()) {
		global $conexion;
		$r = dbQuery($sql, $params);
		if ($r === false) {
			return 0;
		}
		return (int)$conexion->insert_id;
	}

	/** Filas afectadas por la ultima sentencia. */
	function dbAfectadas() {
		return isset($GLOBALS["__dbAfectadas"]) ? (int)$GLOBALS["__dbAfectadas"] : 0;
	}

	/**
	 * Ejecuta $fn dentro de una transaccion. Si lanza excepcion o devuelve
	 * exactamente false, hace rollback y devuelve false; en otro caso commit y
	 * devuelve el resultado de $fn.
	 */
	function dbTransaccion(callable $fn) {
		global $conexion;
		$conexion->begin_transaction();
		try {
			$resultado = $fn($conexion);
			if ($resultado === false) {
				$conexion->rollback();
				return false;
			}
			$conexion->commit();
			return $resultado;
		} catch (Throwable $e) {
			$conexion->rollback();
			appLog('error', 'Transaccion fallida: ' . $e->getMessage());
			return false;
		}
	}

	// ---------- Funciones legacy (solo para SQL sin datos externos) ----------

	function ejecutarConsulta($sql) {
		return dbQuery($sql);
	}

	function ejecutarConsultaSimpleFila($sql) {
		return dbRow($sql);
	}

	function ejecutarConsulta_retornarID($sql) {
		return dbInsert($sql);
	}

	/**
	 * Normaliza texto de entrada: recorta espacios y escapa HTML.
	 * Ya NO escapa para SQL: la proteccion la dan las consultas preparadas.
	 */
	function limpiarCadena($str) {
		$str = trim((string)$str);
		return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
	}

	/** Entero seguro (>=0 por defecto). */
	function enteroSeguro($valor, $min = 0, $default = 0) {
		if ($valor === null || $valor === '' || !is_numeric($valor)) {
			return (int)$default;
		}
		$n = (int)$valor;
		return $n < $min ? (int)$min : $n;
	}

	/** Decimal seguro con redondeo. */
	function decimalSeguro($valor, $decimales = 2, $default = 0.0) {
		if ($valor === null || $valor === '' || !is_numeric($valor)) {
			return (float)$default;
		}
		return round((float)$valor, (int)$decimales);
	}

	/** Fecha YYYY-MM-DD valida o $fallback. */
	function fechaSegura($valor, $fallback = '') {
		$valor = trim((string)$valor);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && strtotime($valor) !== false) {
			return $valor;
		}
		return $fallback;
	}

	// ---------- Moneda ----------

	function obtenerMonedaEmpresaCodigo() {
		static $cachedMoneda = null;
		if ($cachedMoneda !== null) {
			return $cachedMoneda;
		}
		$cachedMoneda = 'PEN';
		$row = dbRow("SELECT moneda FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
		if ($row && isset($row['moneda'])) {
			$moneda = strtoupper(trim((string)$row['moneda']));
			if ($moneda !== '') {
				$cachedMoneda = $moneda;
			}
		}
		return $cachedMoneda;
	}

	function obtenerSimboloMoneda($codigo = null) {
		if ($codigo === null || trim((string)$codigo) === '') {
			$codigo = obtenerMonedaEmpresaCodigo();
		}
		$codigo = strtoupper(trim((string)$codigo));
		$map = array(
			'PEN' => 'S/', 'USD' => '$', 'EUR' => '€', 'MXN' => 'MX$', 'COP' => 'COP$',
			'CLP' => 'CLP$', 'ARS' => 'AR$', 'BOB' => 'Bs', 'UYU' => '$U', 'PYG' => '₲',
			'BRL' => 'R$', 'GTQ' => 'Q', 'CRC' => '₡', 'DOP' => 'RD$', 'HNL' => 'L', 'NIO' => 'C$'
		);
		return isset($map[$codigo]) ? $map[$codigo] : $codigo;
	}

	function obtenerNombreMonedaLetras($codigo = null) {
		if ($codigo === null || trim((string)$codigo) === '') {
			$codigo = obtenerMonedaEmpresaCodigo();
		}
		$codigo = strtoupper(trim((string)$codigo));
		$map = array(
			'PEN' => 'SOLES', 'USD' => 'DOLARES', 'EUR' => 'EUROS', 'MXN' => 'PESOS MEXICANOS',
			'COP' => 'PESOS COLOMBIANOS', 'CLP' => 'PESOS CHILENOS', 'ARS' => 'PESOS ARGENTINOS',
			'BOB' => 'BOLIVIANOS', 'UYU' => 'PESOS URUGUAYOS', 'PYG' => 'GUARANIES', 'BRL' => 'REALES',
			'GTQ' => 'QUETZALES', 'CRC' => 'COLONES', 'DOP' => 'PESOS DOMINICANOS', 'HNL' => 'LEMPIRAS', 'NIO' => 'CORDOBAS'
		);
		return isset($map[$codigo]) ? $map[$codigo] : 'MONEDA';
	}

	function formatearMoneda($monto, $codigo = null, $decimales = 2) {
		$simbolo = obtenerSimboloMoneda($codigo);
		return $simbolo . ' ' . number_format((float)$monto, (int)$decimales, '.', ',');
	}
}

if (!function_exists('textoLatin1')) {
	/** Convierte UTF-8 a ISO-8859-1 para FPDF (reemplaza utf8_decode, obsoleta en PHP 8.2). */
	function textoLatin1($texto) {
		return mb_convert_encoding((string)$texto, 'ISO-8859-1', 'UTF-8');
	}
}
