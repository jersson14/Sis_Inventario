<?php
/**
 * Transferencias de mercaderia entre almacenes.
 *
 * Flujo:
 *  - enviar(): la mercaderia sale del origen y queda "En transito" (almacen
 *    oculto). El total del articulo no cambia. Con lotes, sale en orden FEFO
 *    (o del lote elegido) y viaja con su codigo y vencimiento.
 *  - recibir(): entra al destino lo que llego; lo que falto se da de baja con
 *    un ajuste de salida (motivo TRASLADO) desde el transito. Los lotes se
 *    recrean en el destino (o se suman al mismo lote si ya existe alli).
 *  - anular(): solo si aun no se recibio; todo vuelve al origen.
 *  - "Recibir al instante": envia y recibe en un solo paso (mismo local).
 * Todo en una transaccion por operacion; el kardex muestra TRASLADO - y +.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Stock.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";
require_once "../modelos/Inventario.php";

class Transferencia
{
	private function error($msg)
	{
		return array('ok' => false, 'message' => $msg);
	}

	/**
	 * $lineas: [ {idarticulo, idvariante, idlote (opcional), cantidad} ] en unidades base.
	 * $recibirYa: true = llega al instante (se recibe todo en la misma operacion).
	 * Devuelve array(ok, message, idtransferencia).
	 */
	public function enviar($idorigen, $iddestino, array $lineas, $observacion, $idusuario, $recibirYa = false)
	{
		$idorigen = Stock::almacenValido($idorigen);
		$iddestino = Stock::almacenValido($iddestino);
		if (!$idorigen || !$iddestino) {
			return $this->error('Elige el almacén de origen y el de destino.');
		}
		if ($idorigen === $iddestino) {
			return $this->error('El origen y el destino deben ser almacenes distintos.');
		}
		if (!$lineas) {
			return $this->error('Agrega al menos un producto a la transferencia.');
		}
		$observacion = mb_substr(trim((string)$observacion), 0, 200, 'UTF-8');
		$transito = Stock::transito();
		$usaLotes = Lote::activo();

		// Validar y agrupar lineas
		$items = array();
		$ids = array();
		foreach ($lineas as $l) {
			$ids[] = (int)(isset($l['idarticulo']) ? $l['idarticulo'] : 0);
		}
		$conVariantes = Variante::articulosConVariantes($ids);
		$fraccion = articulosPermitenFraccion($ids);
		foreach ($lineas as $l) {
			$idart = (int)(isset($l['idarticulo']) ? $l['idarticulo'] : 0);
			$nombre = (string)dbValue("SELECT nombre FROM articulo WHERE idarticulo=? AND condicion=1", array($idart), '');
			if ($nombre === '') {
				return $this->error('Uno de los artículos no existe o está desactivado.');
			}
			$var = Variante::resolverDetalle($idart, isset($l['idvariante']) ? $l['idvariante'] : 0, $conVariantes, $nombre);
			if (is_string($var)) {
				return $this->error($var . '.');
			}
			$cant = cantidadSegura(isset($l['cantidad']) ? $l['cantidad'] : 0, !empty($fraccion[$idart]));
			if ($cant <= 0) {
				return $this->error('La cantidad de ' . $nombre . ' debe ser mayor que cero.');
			}
			$items[] = array('idarticulo' => $idart, 'idvariante' => $var ? (int)$var['idvariante'] : 0, 'idlote' => $usaLotes ? (int)(isset($l['idlote']) ? $l['idlote'] : 0) : 0, 'cantidad' => $cant, 'nombre' => $nombre);
		}

		$error = '';
		$res = dbTransaccion(function () use ($idorigen, $iddestino, $items, $observacion, $idusuario, $transito, $usaLotes, &$error) {
			$id = dbInsert(
				"INSERT INTO transferencia (idorigen,iddestino,idusuario,fecha_hora,estado,observacion) VALUES (?,?,?,NOW(),'ENVIADA',?)",
				array($idorigen, $iddestino, (int)$idusuario, $observacion !== '' ? $observacion : null)
			);
			if ($id <= 0) {
				return false;
			}
			$nombreOrigen = Stock::nombre($idorigen);
			foreach ($items as $it) {
				$enOrigen = Stock::enAlmacen($idorigen, $it['idarticulo'], $it['idvariante'] > 0 ? $it['idvariante'] : null, true);
				if ($enOrigen + 0.0005 < $it['cantidad']) {
					$error = 'En ' . $nombreOrigen . ' solo hay ' . formatearCantidad(max(0, $enOrigen)) . ' de ' . $it['nombre'] . '.';
					return false;
				}
				$costoArt = (float)dbValue("SELECT precio_compra FROM articulo WHERE idarticulo=?", array($it['idarticulo']), 0);
				// Lotes: cada tramo que sale de un lote viaja como una linea con su codigo y vencimiento
				$tramos = array();
				if ($usaLotes && $it['idvariante'] === 0) {
					$ref = array('tipo' => 'TRASLADO');
					if ($it['idlote'] > 0) {
						$r = Lote::consumirLote($it['idlote'], $it['idarticulo'], $it['cantidad'], $ref, $idorigen);
						if ($r !== true) {
							$error = $r . '.';
							return false;
						}
						$tramos[] = array('idlote' => $it['idlote'], 'cantidad' => $it['cantidad']);
					} else {
						$consumos = Lote::consumir($it['idarticulo'], $it['cantidad'], $ref, $idorigen);
						if ($consumos === false) {
							return false;
						}
						$tramos = $consumos;
					}
				}
				$enLotes = 0.0;
				foreach ($tramos as $t) {
					$enLotes += $t['cantidad'];
				}
				$sinLote = round($it['cantidad'] - $enLotes, 3);
				foreach ($tramos as $t) {
					$l = dbRow("SELECT codigo_lote, fecha_vencimiento, costo_unitario FROM lote WHERE idlote=?", array((int)$t['idlote']));
					$costo = $l && (float)$l['costo_unitario'] > 0 ? (float)$l['costo_unitario'] : $costoArt;
					$idLoteTransito = Lote::crear($it['idarticulo'], $t['cantidad'], (string)$l['fecha_vencimiento'], (string)$l['codigo_lote'], $costo, null, null, $transito);
					if ($idLoteTransito <= 0 || !$this->linea($id, $it, $t['cantidad'], $idLoteTransito, $l['codigo_lote'], $l['fecha_vencimiento'], $costo)) {
						return false;
					}
				}
				if ($sinLote > 0.0005 && !$this->linea($id, $it, $sinLote, null, null, null, $costoArt)) {
					return false;
				}
				// El total no cambia: pasa del origen al transito
				if (!Stock::moverAlmacen($idorigen, $it['idarticulo'], $it['idvariante'], -$it['cantidad'])
					|| !Stock::moverAlmacen($transito, $it['idarticulo'], $it['idvariante'], $it['cantidad'])) {
					return false;
				}
				if (!Lote::ajustarAlStock($it['idarticulo'])) {
					return false;
				}
			}
			return $id;
		});
		if ($res === false) {
			return $this->error($error !== '' ? $error : 'No se pudo registrar la transferencia.');
		}
		if ($recibirYa) {
			$rec = $this->recibir($res, array(), $idusuario, 'Recibida al enviar');
			if (empty($rec['ok'])) {
				return array('ok' => true, 'message' => 'Transferencia #' . $res . ' enviada, pero no se pudo recibir: ' . $rec['message'], 'idtransferencia' => (int)$res);
			}
			return array('ok' => true, 'message' => 'Transferencia #' . $res . ' registrada: la mercadería ya está en ' . Stock::nombre($iddestino) . '.', 'idtransferencia' => (int)$res);
		}
		return array('ok' => true, 'message' => 'Transferencia #' . $res . ' enviada. Queda en tránsito hasta que ' . Stock::nombre($iddestino) . ' la reciba.', 'idtransferencia' => (int)$res);
	}

	private function linea($idtransf, array $it, $cantidad, $idLoteTransito, $codigo, $vence, $costo)
	{
		return dbInsert(
			"INSERT INTO detalle_transferencia (idtransferencia,idarticulo,idvariante,idlote_transito,lote_codigo,lote_vencimiento,costo_unitario,cantidad) VALUES (?,?,?,?,?,?,?,?)",
			array((int)$idtransf, $it['idarticulo'], $it['idvariante'] > 0 ? $it['idvariante'] : null, $idLoteTransito,
				($codigo !== null && $codigo !== '') ? $codigo : null, ($vence !== null && $vence !== '') ? $vence : null, round((float)$costo, 2), round((float)$cantidad, 3))
		) > 0;
	}

	/**
	 * Recibe una transferencia en su destino. $recibidas: iddetalle => cantidad
	 * que llego (lo que no se indica llego completo). Lo que falta se da de baja
	 * desde el transito (ajuste de salida, motivo TRASLADO).
	 */
	public function recibir($idtransferencia, array $recibidas, $idusuario, $observacion = '')
	{
		$idtransferencia = (int)$idtransferencia;
		$error = '';
		$transito = Stock::transito();
		$res = dbTransaccion(function () use ($idtransferencia, $recibidas, $idusuario, $observacion, $transito, &$error) {
			$t = dbRow("SELECT * FROM transferencia WHERE idtransferencia=? FOR UPDATE", array($idtransferencia));
			if (!$t || $t['estado'] !== 'ENVIADA') {
				$error = 'La transferencia no está pendiente de recibir.';
				return false;
			}
			$destino = (int)$t['iddestino'];
			$faltantes = 0;
			foreach (dbAll("SELECT d.*, a.nombre FROM detalle_transferencia d INNER JOIN articulo a ON a.idarticulo=d.idarticulo WHERE d.idtransferencia=? ORDER BY d.iddetalle", array($idtransferencia)) as $d) {
				$enviada = round((float)$d['cantidad'], 3);
				$llego = isset($recibidas[(int)$d['iddetalle']]) ? round(max(0, (float)$recibidas[(int)$d['iddetalle']]), 3) : $enviada;
				if ($llego > $enviada + 0.0005) {
					$error = 'De ' . $d['nombre'] . ' no puede llegar más de lo enviado (' . formatearCantidad($enviada) . ').';
					return false;
				}
				$idvar = !empty($d['idvariante']) ? (int)$d['idvariante'] : 0;
				// Lo que llego: del transito al destino (con su lote)
				if ($llego > 0) {
					if (!empty($d['idlote_transito'])) {
						$r = Lote::consumirLote((int)$d['idlote_transito'], (int)$d['idarticulo'], $llego, array('tipo' => 'TRASLADO'), $transito);
						if ($r !== true) {
							$error = $r . '.';
							return false;
						}
						if (!$this->loteEnDestino((int)$d['idarticulo'], $destino, $llego, (string)$d['lote_codigo'], (string)$d['lote_vencimiento'], (float)$d['costo_unitario'])) {
							return false;
						}
					}
					if (!Stock::moverAlmacen($transito, (int)$d['idarticulo'], $idvar, -$llego) || !Stock::moverAlmacen($destino, (int)$d['idarticulo'], $idvar, $llego)) {
						return false;
					}
				}
				// Lo que no llego: baja desde el transito
				$falta = round($enviada - $llego, 3);
				if ($falta > 0.0005) {
					$faltantes++;
					$r = Inventario::moverStock(array(
						'idarticulo' => (int)$d['idarticulo'], 'idvariante' => $idvar > 0 ? $idvar : null, 'idusuario' => (int)$idusuario,
						'tipo' => 'SALIDA', 'motivo' => 'TRASLADO', 'cantidad' => $falta, 'costo' => (float)$d['costo_unitario'],
						'observacion' => 'Faltante al recibir la transferencia #' . $idtransferencia, 'idalmacen' => $transito,
						'lote' => !empty($d['idlote_transito']) ? array('modo' => 'lote', 'idlote' => (int)$d['idlote_transito']) : array('modo' => 'ninguno')
					), $error);
					if ($r === false) {
						return false;
					}
				}
				if (!dbExec("UPDATE detalle_transferencia SET cantidad_recibida=? WHERE iddetalle=?", array($llego, (int)$d['iddetalle']))) {
					return false;
				}
				if (!Lote::ajustarAlStock((int)$d['idarticulo'])) {
					return false;
				}
			}
			$ok = dbExec(
				"UPDATE transferencia SET estado='RECIBIDA', idusuario_recibe=?, fecha_recepcion=NOW(), observacion_recepcion=? WHERE idtransferencia=?",
				array((int)$idusuario, mb_substr(trim((string)$observacion), 0, 200, 'UTF-8') ?: null, $idtransferencia)
			);
			return $ok ? array('faltantes' => $faltantes, 'destino' => $destino) : false;
		});
		if ($res === false) {
			return $this->error($error !== '' ? $error : 'No se pudo recibir la transferencia.');
		}
		return array('ok' => true, 'message' => 'Transferencia #' . $idtransferencia . ' recibida en ' . Stock::nombre($res['destino'])
			. ($res['faltantes'] > 0 ? ' · ' . $res['faltantes'] . ' producto(s) con faltante: se dieron de baja.' : '.'), 'faltantes' => $res['faltantes']);
	}

	/** Lote que llega al destino: se suma al mismo lote si ya existe alli, si no se crea. */
	private function loteEnDestino($idarticulo, $destino, $cantidad, $codigo, $vence, $costo)
	{
		$existente = (int)dbValue(
			"SELECT idlote FROM lote WHERE idarticulo=? AND idalmacen=? AND condicion=1 AND IFNULL(codigo_lote,'')=? AND IFNULL(fecha_vencimiento,'')=? ORDER BY idlote LIMIT 1 FOR UPDATE",
			array($idarticulo, $destino, $codigo, $vence),
			0
		);
		if ($existente > 0) {
			return dbExec("UPDATE lote SET stock=ROUND(stock+?,3) WHERE idlote=?", array($cantidad, $existente));
		}
		return Lote::crear($idarticulo, $cantidad, $vence, $codigo, $costo, null, null, $destino) > 0;
	}

	/** Anula una transferencia enviada y no recibida: todo vuelve al origen. */
	public function anular($idtransferencia, $idusuario)
	{
		$idtransferencia = (int)$idtransferencia;
		$error = '';
		$transito = Stock::transito();
		$res = dbTransaccion(function () use ($idtransferencia, $transito, &$error) {
			$t = dbRow("SELECT * FROM transferencia WHERE idtransferencia=? FOR UPDATE", array($idtransferencia));
			if (!$t || $t['estado'] !== 'ENVIADA') {
				$error = 'Solo se anula una transferencia que aún no se recibió.';
				return false;
			}
			$origen = (int)$t['idorigen'];
			foreach (dbAll("SELECT * FROM detalle_transferencia WHERE idtransferencia=?", array($idtransferencia)) as $d) {
				$cant = (float)$d['cantidad'];
				$idvar = !empty($d['idvariante']) ? (int)$d['idvariante'] : 0;
				if (!empty($d['idlote_transito'])) {
					$r = Lote::consumirLote((int)$d['idlote_transito'], (int)$d['idarticulo'], $cant, array('tipo' => 'TRASLADO'), $transito);
					if ($r !== true) {
						$error = $r . '.';
						return false;
					}
					if (!$this->loteEnDestino((int)$d['idarticulo'], $origen, $cant, (string)$d['lote_codigo'], (string)$d['lote_vencimiento'], (float)$d['costo_unitario'])) {
						return false;
					}
				}
				if (!Stock::moverAlmacen($transito, (int)$d['idarticulo'], $idvar, -$cant) || !Stock::moverAlmacen($origen, (int)$d['idarticulo'], $idvar, $cant)) {
					return false;
				}
			}
			return dbExec("UPDATE transferencia SET estado='ANULADA' WHERE idtransferencia=?", array($idtransferencia));
		});
		if ($res === false) {
			return $this->error($error !== '' ? $error : 'No se pudo anular la transferencia.');
		}
		return array('ok' => true, 'message' => 'Transferencia #' . $idtransferencia . ' anulada: la mercadería volvió a su almacén.');
	}

	public function listar($desde, $hasta, $estado = '', $idalmacen = 0)
	{
		$where = array('1=1');
		$params = array();
		if ($desde !== '') { $where[] = 'DATE(t.fecha_hora)>=?'; $params[] = $desde; }
		if ($hasta !== '') { $where[] = 'DATE(t.fecha_hora)<=?'; $params[] = $hasta; }
		if (in_array($estado, array('ENVIADA', 'RECIBIDA', 'ANULADA'), true)) { $where[] = 't.estado=?'; $params[] = $estado; }
		if ((int)$idalmacen > 0) { $where[] = '(t.idorigen=? OR t.iddestino=?)'; $params[] = (int)$idalmacen; $params[] = (int)$idalmacen; }
		return dbAll(
			"SELECT t.*, ao.nombre AS origen, ad.nombre AS destino, u.nombre AS usuario, IFNULL(ur.nombre,'') AS recibio,
				(SELECT COUNT(DISTINCT d.idarticulo) FROM detalle_transferencia d WHERE d.idtransferencia=t.idtransferencia) AS productos,
				(SELECT IFNULL(SUM(d.cantidad),0) FROM detalle_transferencia d WHERE d.idtransferencia=t.idtransferencia) AS unidades,
				(SELECT IFNULL(SUM(d.cantidad*d.costo_unitario),0) FROM detalle_transferencia d WHERE d.idtransferencia=t.idtransferencia) AS valor
			 FROM transferencia t
			 INNER JOIN almacen ao ON ao.idalmacen=t.idorigen
			 INNER JOIN almacen ad ON ad.idalmacen=t.iddestino
			 INNER JOIN usuario u ON u.idusuario=t.idusuario
			 LEFT JOIN usuario ur ON ur.idusuario=t.idusuario_recibe
			 WHERE " . implode(' AND ', $where) . "
			 ORDER BY t.idtransferencia DESC",
			$params
		);
	}

	public function obtener($id)
	{
		$t = dbRow(
			"SELECT t.*, ao.nombre AS origen, ad.nombre AS destino, u.nombre AS usuario, IFNULL(ur.nombre,'') AS recibio
			 FROM transferencia t
			 INNER JOIN almacen ao ON ao.idalmacen=t.idorigen
			 INNER JOIN almacen ad ON ad.idalmacen=t.iddestino
			 INNER JOIN usuario u ON u.idusuario=t.idusuario
			 LEFT JOIN usuario ur ON ur.idusuario=t.idusuario_recibe
			 WHERE t.idtransferencia=?",
			array((int)$id)
		);
		if (!$t) {
			return null;
		}
		$t['detalle'] = dbAll(
			"SELECT d.*, CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(v.talla,''), NULLIF(v.color,'')),''), ')'),'')) AS articulo,
				COALESCE(v.codigo, a.codigo, '') AS codigo, IFNULL(um.abreviatura,'und') AS unidad
			 FROM detalle_transferencia d
			 INNER JOIN articulo a ON a.idarticulo=d.idarticulo
			 LEFT JOIN articulo_variante v ON v.idvariante=d.idvariante
			 LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
			 WHERE d.idtransferencia=? ORDER BY d.iddetalle",
			array((int)$id)
		);
		return $t;
	}
}
