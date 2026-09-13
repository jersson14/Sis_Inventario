<?php
/**
 * Aplica las migraciones SQL pendientes (carpeta migrations/) en orden.
 *
 * Uso (desde la raiz del proyecto):
 *   php scripts/migrar.php            -> aplica las pendientes
 *   php scripts/migrar.php --estado   -> solo muestra el estado
 *   php scripts/migrar.php --forzar archivo.sql -> re-aplica una migracion concreta
 *
 * Registra lo aplicado en la tabla `migracion` (se crea si no existe).
 */
if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo "Solo se puede ejecutar desde la linea de comandos.";
	exit(1);
}

require_once __DIR__ . '/../config/Conexion.php';

$conexion = db();
$dirMigraciones = realpath(__DIR__ . '/../migrations');
$soloEstado = in_array('--estado', $argv, true);
$forzar = '';
$idx = array_search('--forzar', $argv, true);
if ($idx !== false && isset($argv[$idx + 1])) {
	$forzar = basename($argv[$idx + 1]);
}

$conexion->query("CREATE TABLE IF NOT EXISTS `migracion` (
  `idmigracion` INT(11) NOT NULL AUTO_INCREMENT,
  `archivo` VARCHAR(120) NOT NULL,
  `aplicada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idmigracion`),
  UNIQUE KEY `uq_migracion_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$aplicadas = array();
$rs = $conexion->query("SELECT archivo FROM migracion");
while ($row = $rs->fetch_assoc()) {
	$aplicadas[$row['archivo']] = true;
}

$archivos = glob($dirMigraciones . '/*.sql');
sort($archivos);

echo "Base de datos: " . DB_NAME . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

$pendientes = 0;
foreach ($archivos as $ruta) {
	$nombre = basename($ruta);
	$yaAplicada = isset($aplicadas[$nombre]);
	if ($soloEstado) {
		echo ($yaAplicada ? '[OK]       ' : '[PENDIENTE] ') . $nombre . PHP_EOL;
		continue;
	}
	if ($yaAplicada && $forzar !== $nombre) {
		echo "[OK]        $nombre" . PHP_EOL;
		continue;
	}

	echo "[APLICANDO] $nombre ... ";
	$sql = file_get_contents($ruta);
	// Quitar DELIMITER (las migraciones no deben definir triggers/procedimientos)
	$sql = preg_replace('/^DELIMITER .*$/mi', '', $sql);

	$ok = true;
	$error = '';
	if ($conexion->multi_query($sql)) {
		do {
			if ($res = $conexion->store_result()) {
				$res->free();
			}
			if ($conexion->errno) {
				$ok = false;
				$error = $conexion->error;
				break;
			}
		} while ($conexion->more_results() && $conexion->next_result());
		if ($conexion->errno) {
			$ok = false;
			$error = $conexion->error;
		}
	} else {
		$ok = false;
		$error = $conexion->error;
	}

	if ($ok) {
		$stmt = $conexion->prepare("INSERT IGNORE INTO migracion(archivo) VALUES(?)");
		$stmt->bind_param('s', $nombre);
		$stmt->execute();
		$stmt->close();
		echo "listo" . PHP_EOL;
		$pendientes++;
	} else {
		echo "ERROR: $error" . PHP_EOL;
		exit(1);
	}
}

if (!$soloEstado) {
	echo str_repeat('-', 60) . PHP_EOL;
	echo ($pendientes > 0 ? "$pendientes migracion(es) aplicada(s)." : "No habia migraciones pendientes.") . PHP_EOL;
}
