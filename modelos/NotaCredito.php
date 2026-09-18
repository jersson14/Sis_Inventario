<?php
/**
 * Notas de credito: devoluciones parciales o totales de una venta (v2.5).
 *
 * Reglas:
 *  - No se devuelve mas de lo vendido menos lo ya devuelto (por linea vendida).
 *  - La mercaderia vuelve a la misma talla/color y a los mismos lotes de los que
 *    salio. Si esta danada (reingresa_stock = 0) no vuelve a stock: queda en el
 *    kardex y la utilidad la cuenta como perdida.
 *  - Dinero: en una venta al credito la nota primero baja la deuda (pago
 *    NOTA_CREDITO en la cuenta por cobrar); lo que sobra se devuelve por el medio
 *    elegido (egreso en la caja abierta de quien devuelve) o queda como saldo a
 *    favor, que el cliente usa como medio de pago NOTA_CREDITO en otra compra.
 *  - Anular una venta = nota tipo 01 por todo, que revierte cada pago original.
 *  - Numeracion NC01 correlativa asignada por el servidor con bloqueo.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Stock.php";
require_once "../modelos/Lote.php";

class NotaCredito
{
	const SERIE = 'NC01';
	const MEDIOS = array('EFECTIVO', 'YAPE', 'PLIN', 'TARJETA', 'TRANSFERENCIA', 'DEPOSITO', 'OTRO');
	const TIPOS = array('01' => 'Anulación de la operación', '07' => 'Devolución por ítem');

	private function error($msg)
	{
		return array('ok' => false, 'message' => $msg);
	}

	private static function cajaAbierta($idusuario)
	{
		return (int)dbValue("SELECT idcaja FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1", array((int)$idusuario), 0);
	}

	/** Cantidad y descuento ya devueltos por linea vendida (notas emitidas). */
	private static function devueltoPorLinea($idventa)
	{
		$mapa = array();
		foreach (dbAll(
			"SELECT d.iddetalle_venta, SUM(d.cantidad) AS cantidad, SUM(d.descuento) AS descuento
			 FROM detalle_nota_credito d INNER JOIN nota_credito n ON n.idnota=d.idnota
			 WHERE n.idventa=? AND n.estado='EMITIDA'
			 GROUP BY d.iddetalle_venta",
			array((int)$idventa)
		) as $r) {
			$mapa[(int)$r['iddetalle_venta']] = array('cantidad' => round((float)$r['cantidad'], 3), 'descuento' => round((float)$r['descuento'], 2));
		}
		return $mapa;
	}

	/**
	 * Lo que se puede devolver de una venta: cabecera, lineas con vendido,
	 * devuelto y disponible, deuda pendiente y notas anteriores. null si no existe.
	 */
	public function devolvible($idventa)
	{
		$v = dbRow(
			"SELECT v.idventa, v.idcliente, p.nombre AS cliente, v.tipo_comprobante, v.serie_comprobante, v.num_comprobante,
				DATE_FORMAT(v.fecha_hora,'%d/%m/%Y %H:%i') AS fecha, v.total_venta, v.tipo_pago, v.medio_pago, v.estado, v.idusuario
			 FROM venta v INNER JOIN persona p ON p.idpersona=v.idcliente WHERE v.idventa=?",
			array((int)$idventa)
		);
		if (!$v) {
			return null;
		}
		$devuelto = self::devueltoPorLinea($idventa);
		$lineas = array();
		$ids = array();
		$filas = dbAll(
			"SELECT dv.iddetalle_venta, dv.idarticulo, dv.idpresentacion, dv.idvariante, dv.cantidad, dv.factor, dv.precio_venta, dv.descuento,
				CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''), ')'),'')) AS nombre,
				IFNULL(ap.nombre, IFNULL(u.abreviatura,'und')) AS unidad
			 FROM detalle_venta dv
			 INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
			 LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			 LEFT JOIN articulo_presentacion ap ON ap.idpresentacion=dv.idpresentacion
			 LEFT JOIN articulo_variante av ON av.idvariante=dv.idvariante
			 WHERE dv.idventa=? ORDER BY dv.iddetalle_venta",
			array((int)$idventa)
		);
		foreach ($filas as $f) {
			$ids[] = (int)$f['idarticulo'];
		}
		$fraccion = articulosPermitenFraccion($ids);
		foreach ($filas as $f) {
			$id = (int)$f['iddetalle_venta'];
			$dev = isset($devuelto[$id]) ? $devuelto[$id]['cantidad'] : 0.0;
			$lineas[] = array(
				'iddetalle_venta' => $id,
				'nombre' => html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8'),
				'unidad' => html_entity_decode($f['unidad'], ENT_QUOTES, 'UTF-8'),
				'vendido' => round((float)$f['cantidad'], 3),
				'devuelto' => $dev,
				'disponible' => round(max(0, (float)$f['cantidad'] - $dev), 3),
				'precio' => round((float)$f['precio_venta'], 2),
				'descuento' => round((float)$f['descuento'], 2),
				'fraccion' => $f['idpresentacion'] === null && !empty($fraccion[(int)$f['idarticulo']])
			);
		}
		$cuenta = dbRow("SELECT saldo, estado FROM cuenta_cobrar WHERE idventa=? AND estado='PENDIENTE' ORDER BY idcuenta_cobrar DESC LIMIT 1", array((int)$idventa));
		$v['deuda'] = $cuenta ? round((float)$cuenta['saldo'], 2) : 0.0;
		$v['lineas'] = $lineas;
		$v['notas'] = dbAll(
			"SELECT idnota, CONCAT(serie,'-',numero) AS numero, DATE_FORMAT(fecha_hora,'%d/%m/%Y %H:%i') AS fecha, total, tipo_nota FROM nota_credito WHERE idventa=? AND estado='EMITIDA' ORDER BY idnota",
			array((int)$idventa)
		);
		return $v;
	}

	/**
	 * Emite una nota de credito.
	 * $lineas: [ {iddetalle_venta, cantidad, reingresa (bool)} ]
	 * $reintegro: medio (EFECTIVO, YAPE...), SALDO_A_FAVOR u ORIGINAL (solo anulacion total)
	 * $opciones: tipo ('07' | '01'), idautoriza, sin_caja (permite devolver dinero sin caja abierta: administrador)
	 * Devuelve array(ok, message, idnota, numero, total, monto_credito, monto_reintegro, saldo_favor).
	 */
	public function emitir($idventa, $idusuario, array $lineas, $motivo, $reintegro, array $opciones = array())
	{
		$idventa = (int)$idventa;
		$idusuario = (int)$idusuario;
		$tipo = isset($opciones['tipo']) && $opciones['tipo'] === '01' ? '01' : '07';
		$idautoriza = !empty($opciones['idautoriza']) ? (int)$opciones['idautoriza'] : null;
		$sinCaja = !empty($opciones['sin_caja']);
		$motivo = trim(preg_replace('/\s+/', ' ', (string)$motivo));
		$motivo = mb_substr($motivo, 0, 200, 'UTF-8');
		$reintegro = strtoupper(trim((string)$reintegro));
		if (mb_strlen($motivo, 'UTF-8') < 3) {
			return $this->error('Escribe el motivo de la devolución.');
		}
		if ($tipo === '01') {
			$reintegro = 'ORIGINAL';
		} elseif ($reintegro !== 'SALDO_A_FAVOR' && !in_array($reintegro, self::MEDIOS, true)) {
			return $this->error('Elige cómo se devuelve el dinero.');
		}
		if (!$lineas) {
			return $this->error('Indica qué productos se devuelven.');
		}

		$error = '';
		$res = dbTransaccion(function () use ($idventa, $idusuario, $lineas, $motivo, $reintegro, $tipo, $idautoriza, $sinCaja, &$error) {
			$venta = dbRow("SELECT * FROM venta WHERE idventa=? FOR UPDATE", array($idventa));
			if (!$venta) {
				$error = 'La venta no existe.';
				return false;
			}
			if ($venta['estado'] !== 'Aceptado') {
				$error = 'La venta ya está anulada.';
				return false;
			}
			if ($tipo === '01' && (int)dbValue("SELECT COUNT(*) FROM nota_credito WHERE idventa=? AND estado='EMITIDA'", array($idventa), 0) > 0) {
				$error = 'La venta ya tiene devoluciones: devuelve lo que queda con "Devolver" en lugar de anularla.';
				return false;
			}
			$documento = $venta['tipo_comprobante'] . ' ' . $venta['serie_comprobante'] . '-' . $venta['num_comprobante'];

			// Lineas: tope por lo vendido menos lo ya devuelto
			$det = array();
			foreach (dbAll("SELECT dv.*, a.nombre FROM detalle_venta dv INNER JOIN articulo a ON a.idarticulo=dv.idarticulo WHERE dv.idventa=? FOR UPDATE", array($idventa)) as $d) {
				$det[(int)$d['iddetalle_venta']] = $d;
			}
			$devuelto = self::devueltoPorLinea($idventa);
			$fraccion = articulosPermitenFraccion(array_map(function ($d) { return (int)$d['idarticulo']; }, $det));
			$items = array();
			$total = 0.0;
			$vistos = array();
			foreach ($lineas as $l) {
				$idd = (int)(isset($l['iddetalle_venta']) ? $l['iddetalle_venta'] : 0);
				if (!isset($det[$idd])) {
					$error = 'Una de las líneas no pertenece a esta venta.';
					return false;
				}
				if (isset($vistos[$idd])) {
					$error = 'Una línea se repite en la devolución.';
					return false;
				}
				$vistos[$idd] = true;
				$d = $det[$idd];
				$permiteFraccion = $d['idpresentacion'] === null && !empty($fraccion[(int)$d['idarticulo']]);
				$cant = cantidadSegura(isset($l['cantidad']) ? $l['cantidad'] : 0, $permiteFraccion);
				if ($cant <= 0) {
					continue;
				}
				$yaDev = isset($devuelto[$idd]) ? $devuelto[$idd] : array('cantidad' => 0.0, 'descuento' => 0.0);
				$disponible = round((float)$d['cantidad'] - $yaDev['cantidad'], 3);
				if ($cant > $disponible + 0.0005) {
					$error = 'De ' . html_entity_decode($d['nombre'], ENT_QUOTES, 'UTF-8') . ' solo se pueden devolver ' . formatearCantidad(max(0, $disponible)) . '.';
					return false;
				}
				// Descuento proporcional; la ultima devolucion de la linea se lleva el resto (sin centimos perdidos)
				$desc = abs($cant - $disponible) < 0.0005
					? round((float)$d['descuento'] - $yaDev['descuento'], 2)
					: round((float)$d['descuento'] * $cant / (float)$d['cantidad'], 2);
				$sub = round($cant * (float)$d['precio_venta'] - $desc, 2);
				$items[] = array('d' => $d, 'cantidad' => $cant, 'descuento' => $desc, 'subtotal' => $sub, 'reingresa' => $tipo === '01' || !isset($l['reingresa']) || !empty($l['reingresa']));
				$total += $sub;
			}
			if (!$items) {
				$error = 'Indica la cantidad a devolver de al menos un producto.';
				return false;
			}
			$total = round($total, 2);

			// Numero correlativo con bloqueo
			$max = (int)dbValue("SELECT IFNULL(MAX(CAST(numero AS UNSIGNED)),0) FROM nota_credito WHERE serie=? FOR UPDATE", array(self::SERIE), 0);
			$numero = str_pad((string)($max + 1), 8, '0', STR_PAD_LEFT);
			$docNota = self::SERIE . '-' . $numero;

			$idnota = dbInsert(
				"INSERT INTO nota_credito (idventa,idcliente,idalmacen,idusuario,idautoriza,serie,numero,fecha_hora,tipo_nota,motivo,total,reintegro,estado)
				 VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,'EMITIDA')",
				array($idventa, (int)$venta['idcliente'], !empty($venta['idalmacen']) ? (int)$venta['idalmacen'] : Stock::principal(), $idusuario, $idautoriza, self::SERIE, $numero, $tipo, $motivo, $total, $reintegro)
			);
			if ($idnota <= 0) {
				return false;
			}

			// Lineas, stock (misma talla) y lotes (los mismos de los que salio)
			$usaLotes = Lote::activo();
			foreach ($items as $it) {
				$d = $it['d'];
				$ok = dbInsert(
					"INSERT INTO detalle_nota_credito (idnota,iddetalle_venta,idarticulo,idpresentacion,idvariante,cantidad,factor,precio,descuento,subtotal,reingresa_stock)
					 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
					array($idnota, (int)$d['iddetalle_venta'], (int)$d['idarticulo'], $d['idpresentacion'], $d['idvariante'], $it['cantidad'], (float)$d['factor'],
						(float)$d['precio_venta'], $it['descuento'], $it['subtotal'], $it['reingresa'] ? 1 : 0)
				) > 0;
				if (!$ok) {
					return false;
				}
				if ($it['reingresa']) {
					$unidades = round($it['cantidad'] * (float)$d['factor'], 3);
					// Vuelve al almacen del que salio la venta
					if (!Stock::mover((int)$d['idarticulo'], $d['idvariante'], $unidades, !empty($venta['idalmacen']) ? (int)$venta['idalmacen'] : 0)) {
						return false;
					}
					if (!Lote::devolverLinea((int)$d['iddetalle_venta'], $idventa, $unidades, $idnota)) {
						return false;
					}
				}
			}
			if (!$usaLotes) {
				foreach ($items as $it) {
					Lote::ajustarAlStock((int)$it['d']['idarticulo']);
				}
			}

			// Dinero
			$montoCredito = 0.0;
			$montoReintegro = 0.0;
			$saldoFavor = 0.0;
			$idcajaMov = 0;
			$cuenta = $venta['tipo_pago'] === 'CREDITO'
				? dbRow("SELECT * FROM cuenta_cobrar WHERE idventa=? AND estado='PENDIENTE' ORDER BY idcuenta_cobrar DESC LIMIT 1 FOR UPDATE", array($idventa))
				: null;
			$egreso = function ($idcaja, $medio, $monto) use ($idusuario, $docNota, $documento, $idnota) {
				return dbInsert(
					"INSERT INTO caja_movimiento (idcaja,idusuario,tipo,concepto,referencia,medio_pago,monto,fecha_hora) VALUES (?,?,'EGRESO',?,?,?,?,NOW())",
					array($idcaja, $idusuario, substr('Devolución ' . $docNota . ' de ' . $documento, 0, 120), 'NC-' . $idnota, $medio, round($monto, 2))
				) > 0;
			};

			if ($reintegro === 'ORIGINAL') {
				// Anulacion: la deuda se anula (si no tiene cobros) y cada pago vuelve por su medio
				if ($cuenta) {
					$cobros = (int)dbValue("SELECT COUNT(*) FROM pago_cuenta_cobrar WHERE idcuenta_cobrar=?", array((int)$cuenta['idcuenta_cobrar']), 0);
					if ($cobros > 0) {
						$error = 'La venta tiene cobros registrados; anula primero los pagos o registra una devolución.';
						return false;
					}
					$montoCredito = round((float)$cuenta['saldo'], 2);
					if (!dbExec("UPDATE cuenta_cobrar SET estado='ANULADO', saldo=0 WHERE idcuenta_cobrar=?", array((int)$cuenta['idcuenta_cobrar']))) {
						return false;
					}
				}
				$cajaUsuario = self::cajaAbierta($idusuario);
				foreach (dbAll("SELECT vp.*, IFNULL(c.estado,'') AS estado_caja FROM venta_pago vp LEFT JOIN caja_diaria c ON c.idcaja=vp.idcaja WHERE vp.idventa=? ORDER BY vp.idpago", array($idventa)) as $p) {
					$montoReintegro += (float)$p['monto'];
					if ($p['medio_pago'] === 'NOTA_CREDITO') {
						if (!empty($p['idnota']) && !dbExec("UPDATE nota_credito SET saldo_favor=saldo_favor+? WHERE idnota=?", array((float)$p['monto'], (int)$p['idnota']))) {
							return false;
						}
						continue;
					}
					// A la caja donde entro si sigue abierta; si no, a la caja abierta de quien anula
					$idcaja = $p['estado_caja'] === 'ABIERTA' ? (int)$p['idcaja'] : $cajaUsuario;
					if ($idcaja > 0) {
						if (!$egreso($idcaja, $p['medio_pago'], (float)$p['monto'])) {
							return false;
						}
						$idcajaMov = $idcaja;
					}
				}
				if (!dbExec("UPDATE venta SET estado='Anulado' WHERE idventa=?", array($idventa))) {
					return false;
				}
			} else {
				// Devolucion: primero baja la deuda de una venta al credito
				if ($cuenta && (float)$cuenta['saldo'] > 0.004) {
					$montoCredito = round(min($total, (float)$cuenta['saldo']), 2);
					$ok = dbInsert(
						"INSERT INTO pago_cuenta_cobrar (idcuenta_cobrar,idusuario,monto,medio_pago,observacion) VALUES (?,?,?,'NOTA_CREDITO',?)",
						array((int)$cuenta['idcuenta_cobrar'], $idusuario, $montoCredito, 'Nota de crédito ' . $docNota)
					) > 0;
					$nuevo = round((float)$cuenta['saldo'] - $montoCredito, 2);
					if (!$ok || !dbExec("UPDATE cuenta_cobrar SET saldo=?, estado=? WHERE idcuenta_cobrar=?", array(max(0, $nuevo), $nuevo <= 0.004 ? 'PAGADO' : 'PENDIENTE', (int)$cuenta['idcuenta_cobrar']))) {
						return false;
					}
				}
				$resto = round($total - $montoCredito, 2);
				if ($resto > 0.004) {
					$montoReintegro = $resto;
					if ($reintegro === 'SALDO_A_FAVOR') {
						$saldoFavor = $resto;
					} else {
						$idcaja = self::cajaAbierta($idusuario);
						if ($idcaja <= 0 && !$sinCaja) {
							$error = 'Abre tu caja para devolver dinero, o deja el importe como saldo a favor del cliente.';
							return false;
						}
						if ($idcaja > 0) {
							if (!$egreso($idcaja, $reintegro, $resto)) {
								return false;
							}
							$idcajaMov = $idcaja;
						}
					}
				}
			}

			if (!dbExec(
				"UPDATE nota_credito SET monto_credito=?, monto_reintegro=?, saldo_favor=?, idcaja=? WHERE idnota=?",
				array(round($montoCredito, 2), round($montoReintegro, 2), round($saldoFavor, 2), $idcajaMov > 0 ? $idcajaMov : null, $idnota)
			)) {
				return false;
			}
			return array('idnota' => $idnota, 'numero' => $docNota, 'total' => $total, 'monto_credito' => round($montoCredito, 2),
				'monto_reintegro' => round($montoReintegro, 2), 'saldo_favor' => round($saldoFavor, 2), 'en_caja' => $idcajaMov > 0, 'documento' => $documento);
		});

		if ($res === false) {
			return $this->error($error !== '' ? $error : 'No se pudo registrar la devolución.');
		}
		$msg = 'Nota de crédito ' . $res['numero'] . ' por ' . number_format($res['total'], 2);
		if ($res['monto_credito'] > 0 && $reintegro !== 'ORIGINAL') {
			$msg .= ' · deuda reducida en ' . number_format($res['monto_credito'], 2);
		}
		if ($res['saldo_favor'] > 0) {
			$msg .= ' · saldo a favor ' . number_format($res['saldo_favor'], 2);
		} elseif ($res['monto_reintegro'] > 0 && $reintegro !== 'ORIGINAL') {
			$msg .= ' · devolver ' . number_format($res['monto_reintegro'], 2) . ($res['en_caja'] ? ' (salida de caja)' : ' (sin caja abierta: no quedó en caja)');
		}
		return array('ok' => true, 'message' => $msg) + $res;
	}

	/** Anula una venta completa: nota 01 por todo lo vendido, revirtiendo cada pago. */
	public function anularVenta($idventa, $idusuario, $motivo, $idautoriza = null)
	{
		$lineas = array();
		foreach (dbAll("SELECT iddetalle_venta, cantidad FROM detalle_venta WHERE idventa=?", array((int)$idventa)) as $d) {
			$lineas[] = array('iddetalle_venta' => (int)$d['iddetalle_venta'], 'cantidad' => (float)$d['cantidad'], 'reingresa' => true);
		}
		if (!$lineas) {
			return $this->error('La venta no existe o no tiene detalle.');
		}
		// Quien tiene el permiso de anular no esta obligado a escribir el motivo
		$motivo = trim((string)$motivo);
		$motivo = 'Anulación de la venta' . ($motivo !== '' ? ': ' . $motivo : '');
		return $this->emitir($idventa, $idusuario, $lineas, $motivo, 'ORIGINAL', array('tipo' => '01', 'idautoriza' => $idautoriza, 'sin_caja' => true));
	}

	// ---------- Saldo a favor (medio de pago NOTA_CREDITO) ----------

	/** Notas con saldo a favor de un cliente. */
	public function saldosCliente($idcliente)
	{
		return dbAll(
			"SELECT idnota, CONCAT(serie,'-',numero) AS numero, saldo_favor, DATE_FORMAT(fecha_hora,'%d/%m/%Y') AS fecha
			 FROM nota_credito WHERE idcliente=? AND estado='EMITIDA' AND saldo_favor>0 ORDER BY idnota",
			array((int)$idcliente)
		);
	}

	/**
	 * Usa saldo a favor como pago (dentro de la transaccion de la venta).
	 * $ref: "NC01-00000003", "00000003" o "3". Devuelve el idnota o un mensaje de error.
	 */
	public static function consumirSaldo($ref, $idcliente, $monto)
	{
		$ref = strtoupper(trim((string)$ref));
		$numero = preg_replace('/^NC0?1-?/', '', $ref);
		if (!preg_match('/^\d{1,8}$/', $numero)) {
			return 'Indica el número de la nota de crédito (ej. NC01-00000003)';
		}
		$n = dbRow(
			"SELECT idnota, idcliente, saldo_favor, estado FROM nota_credito WHERE serie=? AND numero=? FOR UPDATE",
			array(self::SERIE, str_pad($numero, 8, '0', STR_PAD_LEFT))
		);
		if (!$n || $n['estado'] !== 'EMITIDA') {
			return 'La nota de crédito ' . $ref . ' no existe';
		}
		if ((int)$n['idcliente'] !== (int)$idcliente) {
			return 'La nota de crédito ' . $ref . ' es de otro cliente';
		}
		if ((float)$n['saldo_favor'] + 0.004 < (float)$monto) {
			return 'La nota de crédito ' . $ref . ' solo tiene ' . number_format((float)$n['saldo_favor'], 2) . ' de saldo a favor';
		}
		if (!dbExec("UPDATE nota_credito SET saldo_favor=ROUND(saldo_favor-?,2) WHERE idnota=?", array(round((float)$monto, 2), (int)$n['idnota']))) {
			return 'No se pudo usar el saldo a favor';
		}
		return (int)$n['idnota'];
	}

	// ---------- Consultas ----------

	public function listar($desde, $hasta, $idusuario = 0)
	{
		$where = array('1=1');
		$params = array();
		if ($desde !== '') {
			$where[] = 'DATE(n.fecha_hora)>=?';
			$params[] = $desde;
		}
		if ($hasta !== '') {
			$where[] = 'DATE(n.fecha_hora)<=?';
			$params[] = $hasta;
		}
		if ((int)$idusuario > 0) {
			$where[] = 'n.idusuario=?';
			$params[] = (int)$idusuario;
		}
		return dbAll(
			"SELECT n.idnota, n.serie, n.numero, n.fecha_hora, n.tipo_nota, n.motivo, n.total, n.reintegro, n.monto_credito, n.monto_reintegro, n.saldo_favor, n.estado,
				v.tipo_comprobante, v.serie_comprobante, v.num_comprobante, p.nombre AS cliente, u.nombre AS usuario, IFNULL(ua.nombre,'') AS autorizo
			 FROM nota_credito n
			 INNER JOIN venta v ON v.idventa=n.idventa
			 INNER JOIN persona p ON p.idpersona=n.idcliente
			 INNER JOIN usuario u ON u.idusuario=n.idusuario
			 LEFT JOIN usuario ua ON ua.idusuario=n.idautoriza
			 WHERE " . implode(' AND ', $where) . "
			 ORDER BY n.idnota DESC",
			$params
		);
	}

	public function cabecera($idnota)
	{
		return dbRow(
			"SELECT n.*, DATE_FORMAT(n.fecha_hora,'%d/%m/%Y %H:%i') AS fecha, v.tipo_comprobante, v.serie_comprobante, v.num_comprobante, v.tipo_pago,
				DATE_FORMAT(v.fecha_hora,'%d/%m/%Y') AS fecha_venta, v.idusuario AS idvendedor,
				p.nombre AS cliente, p.tipo_documento, p.num_documento, p.direccion, u.nombre AS usuario, IFNULL(ua.nombre,'') AS autorizo
			 FROM nota_credito n
			 INNER JOIN venta v ON v.idventa=n.idventa
			 INNER JOIN persona p ON p.idpersona=n.idcliente
			 INNER JOIN usuario u ON u.idusuario=n.idusuario
			 LEFT JOIN usuario ua ON ua.idusuario=n.idautoriza
			 WHERE n.idnota=?",
			array((int)$idnota)
		);
	}

	public function detalle($idnota)
	{
		return dbAll(
			"SELECT d.*, CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''), ')'),'')) AS articulo,
				COALESCE(av.codigo, ap.codigo, a.codigo) AS codigo, IFNULL(ap.nombre, IFNULL(u.abreviatura,'und')) AS unidad
			 FROM detalle_nota_credito d
			 INNER JOIN articulo a ON a.idarticulo=d.idarticulo
			 LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			 LEFT JOIN articulo_presentacion ap ON ap.idpresentacion=d.idpresentacion
			 LEFT JOIN articulo_variante av ON av.idvariante=d.idvariante
			 WHERE d.idnota=? ORDER BY d.iddetalle",
			array((int)$idnota)
		);
	}
}
