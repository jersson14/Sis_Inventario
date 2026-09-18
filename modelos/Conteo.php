<?php
/**
 * Toma de inventario: conteo fisico con lector de barras.
 *
 * Como funciona:
 *  - Se abre un conteo (todo el almacen o una categoria). Solo uno abierto a
 *    la vez: dos conteos abiertos aplicarian dos veces la misma diferencia.
 *  - Cada lectura suma a una linea por articulo / talla-color / lote. Varias
 *    personas pueden contar a la vez (cada lectura va al servidor).
 *  - Cada linea guarda el stock que tenia el sistema en su ultima lectura. Al
 *    aplicar se ajusta la diferencia (contado - stock_sistema) sobre el stock
 *    actual: si durante el conteo se vendio algo ya contado, no se descuadra.
 *  - Con "por lote" (rubros con lotes) se cuenta cada lote por separado, el
 *    stock sin lote aparte y los lotes nuevos que aparezcan (codigo/fecha).
 *  - Cada conteo es de un almacen: se compara y se ajusta el stock de ese almacen.
 *  - Al aplicar se generan ajustes de inventario con motivo CONTEO
 *    (ajuste_inventario.idconteo), que el kardex ya muestra. Opcionalmente se
 *    ponen en cero los articulos, tallas y lotes del alcance que no se contaron.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";
require_once "../modelos/Inventario.php";
require_once "../modelos/Stock.php";

class Conteo
{
	/** Conteo abierto (a lo mas uno) o null. */
	public static function abierto()
	{
		return dbRow(
			"SELECT c.*, IFNULL(cat.nombre,'') AS categoria, u.nombre AS usuario, IFNULL(al.nombre,'') AS almacen
			 FROM conteo_inventario c
			 LEFT JOIN almacen al ON al.idalmacen=c.idalmacen
			 LEFT JOIN categoria cat ON cat.idcategoria=c.idcategoria
			 INNER JOIN usuario u ON u.idusuario=c.idusuario
			 WHERE c.estado='ABIERTO' ORDER BY c.idconteo DESC LIMIT 1"
		);
	}

	public function obtener($idconteo)
	{
		return dbRow(
			"SELECT c.*, IFNULL(cat.nombre,'') AS categoria, u.nombre AS usuario, IFNULL(uc.nombre,'') AS usuario_cierre, IFNULL(al.nombre,'') AS almacen
			 FROM conteo_inventario c
			 LEFT JOIN almacen al ON al.idalmacen=c.idalmacen
			 LEFT JOIN categoria cat ON cat.idcategoria=c.idcategoria
			 INNER JOIN usuario u ON u.idusuario=c.idusuario
			 LEFT JOIN usuario uc ON uc.idusuario=c.idusuario_cierre
			 WHERE c.idconteo=?",
			array((int)$idconteo)
		);
	}

	/** Almacen del conteo (los conteos anteriores a los almacenes: el principal). */
	public static function almacen($conteo)
	{
		return $conteo && !empty($conteo['idalmacen']) ? (int)$conteo['idalmacen'] : Stock::principal();
	}

	/** ¿Este conteo se cuenta por lote? (depende tambien del rubro actual) */
	public static function porLote($conteo)
	{
		return $conteo && (int)$conteo['por_lote'] === 1 && Lote::activo();
	}

	public function crear($nombre, $idcategoria, $porLote, $observacion, $idusuario, $idalmacen = 0)
	{
		$nombre = trim(preg_replace('/\s+/', ' ', (string)$nombre));
		if ($nombre === '') {
			$nombre = 'Conteo ' . date('d/m/Y');
		}
		$nombre = mb_substr($nombre, 0, 80, 'UTF-8');
		$idcategoria = (int)$idcategoria;
		if ($idcategoria > 0 && !dbValue("SELECT idcategoria FROM categoria WHERE idcategoria=?", array($idcategoria))) {
			return array('ok' => false, 'message' => 'La categoría no existe.');
		}
		$porLote = $porLote && Lote::activo() ? 1 : 0;
		$idalmacen = ((int)$idalmacen > 0 && Stock::almacenValido($idalmacen)) ? (int)$idalmacen : Stock::principal();
		$error = '';
		$id = dbTransaccion(function () use ($nombre, $idcategoria, $porLote, $observacion, $idusuario, $idalmacen, &$error) {
			// El conteo parte de un stock por almacen cuadrado con el total
			if (!Stock::cuadrarDescuadrados()) {
				return false;
			}
			$abierto = dbRow("SELECT idconteo, nombre FROM conteo_inventario WHERE estado='ABIERTO' LIMIT 1 FOR UPDATE");
			if ($abierto) {
				$error = 'Ya hay un conteo abierto (' . $abierto['nombre'] . '). Aplícalo o anúlalo antes de empezar otro.';
				return false;
			}
			$id = dbInsert(
				"INSERT INTO conteo_inventario (nombre,idcategoria,idalmacen,por_lote,estado,observacion,idusuario,fecha_inicio) VALUES (?,?,?,?,'ABIERTO',?,?,NOW())",
				array($nombre, $idcategoria > 0 ? $idcategoria : null, $idalmacen, $porLote, mb_substr((string)$observacion, 0, 200, 'UTF-8'), (int)$idusuario)
			);
			return $id > 0 ? $id : false;
		});
		if ($id === false) {
			return array('ok' => false, 'message' => $error !== '' ? $error : 'No se pudo crear el conteo.');
		}
		return array('ok' => true, 'message' => 'Conteo iniciado. Escanea los productos.', 'idconteo' => (int)$id);
	}

	/** Historial de conteos. */
	public function listar()
	{
		return dbAll(
			"SELECT c.idconteo, c.nombre, c.estado, c.por_lote, c.fecha_inicio, c.fecha_cierre, c.ajustes, c.valor_sobrante, c.valor_faltante,
				IFNULL(cat.nombre,'') AS categoria, u.nombre AS usuario, (SELECT al.nombre FROM almacen al WHERE al.idalmacen=c.idalmacen) AS almacen,
				(SELECT COUNT(*) FROM conteo_detalle d WHERE d.idconteo=c.idconteo) AS lineas
			 FROM conteo_inventario c
			 LEFT JOIN categoria cat ON cat.idcategoria=c.idcategoria
			 INNER JOIN usuario u ON u.idusuario=c.idusuario
			 ORDER BY c.idconteo DESC"
		);
	}

	/** Articulo activo dentro del alcance del conteo, o null. */
	private function articuloEnAlcance($conteo, $idarticulo, $bloquear = false)
	{
		$a = dbRow(
			"SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, a.stock, a.precio_compra, a.idcategoria, a.condicion,
				IFNULL(u.abreviatura,'und') AS unidad, IFNULL(u.permite_fraccion,0) AS permite_fraccion
			 FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			 WHERE a.idarticulo=?" . ($bloquear ? " FOR UPDATE" : ""),
			array((int)$idarticulo)
		);
		if (!$a || (int)$a['condicion'] !== 1) {
			return null;
		}
		if (!empty($conteo['idcategoria']) && (int)$a['idcategoria'] !== (int)$conteo['idcategoria']) {
			return null;
		}
		return $a;
	}

	/**
	 * Todo lo que la pantalla necesita para contar un articulo: tallas/colores
	 * o lotes con su stock y lo ya contado. null si no esta en el alcance.
	 */
	public function fichaArticulo($conteo, $idarticulo)
	{
		$a = $this->articuloEnAlcance($conteo, $idarticulo);
		if (!$a) {
			return null;
		}
		$id = (int)$a['idarticulo'];
		$contado = array();
		foreach (dbAll("SELECT idvariante, idlote, lote_clave, lote_codigo, lote_vencimiento, cantidad FROM conteo_detalle WHERE idconteo=? AND idarticulo=?", array((int)$conteo['idconteo'], $id)) as $l) {
			$contado[$l['idvariante'] . '|' . $l['idlote'] . '|' . $l['lote_clave']] = $l;
		}
		$fraccion = articulosPermitenFraccion(array($id));
		$ficha = array(
			'idarticulo' => $id,
			'nombre' => html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8'),
			'codigo' => $a['codigo'],
			'unidad' => $a['unidad'],
			'fraccion' => !empty($fraccion[$id]),
			'variantes' => array(),
			'por_lote' => false,
			'lotes' => array(),
			'lotes_nuevos' => array(),
			'stock_sin_lote' => 0,
			'contado_sin_lote' => null,
			'contado' => null
		);
		$variantes = Variante::deArticulo($id);
		if ($variantes) {
			foreach ($variantes as $v) {
				$k = $v['idvariante'] . '|0|';
				$ficha['variantes'][] = array(
					'idvariante' => (int)$v['idvariante'],
					'etiqueta' => html_entity_decode(Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'),
					'codigo' => (string)$v['codigo'],
					'contado' => isset($contado[$k]) ? round((float)$contado[$k]['cantidad'], 3) : null
				);
			}
			return $ficha;
		}
		if (self::porLote($conteo)) {
			$ficha['por_lote'] = true;
			foreach (Lote::deArticulo($id, self::almacen($conteo)) as $l) {
				$k = '0|' . $l['idlote'] . '|';
				$ficha['lotes'][] = array(
					'idlote' => (int)$l['idlote'],
					'codigo_lote' => (string)$l['codigo_lote'],
					'fecha_vencimiento' => $l['fecha_vencimiento'],
					'dias' => $l['dias'] === null ? null : (int)$l['dias'],
					'contado' => isset($contado[$k]) ? round((float)$contado[$k]['cantidad'], 3) : null
				);
			}
			// Lotes que ya se contaron aunque hoy no tengan stock, y lotes nuevos
			foreach ($contado as $l) {
				if ((int)$l['idlote'] === 0 && $l['lote_clave'] !== '') {
					$ficha['lotes_nuevos'][] = array('codigo_lote' => (string)$l['lote_codigo'], 'fecha_vencimiento' => $l['lote_vencimiento'], 'contado' => round((float)$l['cantidad'], 3));
				}
			}
			$k = '0|0|';
			$ficha['contado_sin_lote'] = isset($contado[$k]) ? round((float)$contado[$k]['cantidad'], 3) : null;
			return $ficha;
		}
		$k = '0|0|';
		$ficha['contado'] = isset($contado[$k]) ? round((float)$contado[$k]['cantidad'], 3) : null;
		return $ficha;
	}

	/**
	 * Registra una lectura. $p: idarticulo, idvariante, idlote, lote_codigo,
	 * lote_vencimiento, cantidad, modo ('sumar' | 'fijar').
	 * Devuelve array(ok, message, linea).
	 */
	public function registrar($idconteo, array $p, $idusuario)
	{
		$idconteo = (int)$idconteo;
		$error = '';
		$res = dbTransaccion(function () use ($idconteo, $p, $idusuario, &$error) {
			$conteo = dbRow("SELECT * FROM conteo_inventario WHERE idconteo=? FOR UPDATE", array($idconteo));
			if (!$conteo || $conteo['estado'] !== 'ABIERTO') {
				$error = 'El conteo ya no está abierto.';
				return false;
			}
			$a = $this->articuloEnAlcance($conteo, isset($p['idarticulo']) ? $p['idarticulo'] : 0);
			if (!$a) {
				$error = !empty($conteo['idcategoria']) ? 'Ese artículo no es de la categoría que estás contando.' : 'El artículo no existe o está desactivado.';
				return false;
			}
			$idarticulo = (int)$a['idarticulo'];
			$nombre = html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8');

			// Talla/color obligatoria si el articulo las tiene
			$conVariantes = Variante::articulosConVariantes(array($idarticulo));
			$variante = Variante::resolverDetalle($idarticulo, isset($p['idvariante']) ? $p['idvariante'] : 0, $conVariantes, $nombre);
			if (is_string($variante)) {
				$error = $variante . '.';
				return false;
			}
			$idvariante = $variante ? (int)$variante['idvariante'] : 0;

			// Lote: solo en conteos por lote y en articulos sin tallas
			$idlote = 0;
			$loteClave = '';
			$loteCodigo = null;
			$loteVence = null;
			$costo = (float)$a['precio_compra'];
			$porLote = self::porLote($conteo) && $idvariante === 0;
			if ($porLote) {
				$idlote = isset($p['idlote']) ? (int)$p['idlote'] : 0;
				if ($idlote > 0) {
					$l = dbRow("SELECT idlote, costo_unitario FROM lote WHERE idlote=? AND idarticulo=? AND idalmacen=? AND condicion=1", array($idlote, $idarticulo, self::almacen($conteo)));
					if (!$l) {
						$error = 'El lote no existe o no es de ' . $nombre . '.';
						return false;
					}
					if ((float)$l['costo_unitario'] > 0) {
						$costo = (float)$l['costo_unitario'];
					}
				} else {
					$codigo = Lote::codigoValido(isset($p['lote_codigo']) ? $p['lote_codigo'] : '');
					$vence = Lote::fechaValida(isset($p['lote_vencimiento']) ? $p['lote_vencimiento'] : '');
					if ($vence === false) {
						$error = 'La fecha de vencimiento no es válida.';
						return false;
					}
					if ($codigo !== '' || $vence !== '') {
						// Si ese lote ya existe con stock, se cuenta en el mismo
						$existente = dbRow(
							"SELECT idlote, costo_unitario FROM lote WHERE idarticulo=? AND idalmacen=? AND condicion=1 AND IFNULL(codigo_lote,'')=? AND IFNULL(fecha_vencimiento,'')=? ORDER BY stock DESC LIMIT 1",
							array($idarticulo, self::almacen($conteo), $codigo, $vence)
						);
						if ($existente) {
							$idlote = (int)$existente['idlote'];
							if ((float)$existente['costo_unitario'] > 0) {
								$costo = (float)$existente['costo_unitario'];
							}
						} else {
							$loteClave = mb_substr($codigo . '|' . $vence, 0, 60, 'UTF-8');
							$loteCodigo = $codigo !== '' ? $codigo : null;
							$loteVence = $vence !== '' ? $vence : null;
						}
					}
				}
			}

			$fraccion = articulosPermitenFraccion(array($idarticulo));
			$cantidad = cantidadSegura(isset($p['cantidad']) ? $p['cantidad'] : 0, !empty($fraccion[$idarticulo]));
			$modo = (isset($p['modo']) && $p['modo'] === 'fijar') ? 'fijar' : 'sumar';
			if ($modo === 'sumar' && $cantidad <= 0) {
				$error = 'La cantidad debe ser mayor que cero.';
				return false;
			}
			if ($cantidad > 9999999) {
				$error = 'La cantidad es demasiado grande.';
				return false;
			}

			$stockSistema = $this->stockActualLinea($idarticulo, $idvariante, $idlote, $loteClave, $porLote, self::almacen($conteo));
			$ok = dbExec(
				"INSERT INTO conteo_detalle (idconteo,idarticulo,idvariante,idlote,lote_clave,lote_codigo,lote_vencimiento,cantidad,stock_sistema,costo_unitario,lecturas,idusuario,actualizado)
				 VALUES (?,?,?,?,?,?,?,?,?,?,1,?,NOW())
				 ON DUPLICATE KEY UPDATE cantidad=IF(?=1, cantidad+VALUES(cantidad), VALUES(cantidad)), stock_sistema=VALUES(stock_sistema),
					costo_unitario=VALUES(costo_unitario), lecturas=lecturas+1, idusuario=VALUES(idusuario), actualizado=NOW()",
				array($idconteo, $idarticulo, $idvariante, $idlote, $loteClave, $loteCodigo, $loteVence, $cantidad, $stockSistema, round($costo, 2), (int)$idusuario, $modo === 'sumar' ? 1 : 0)
			);
			if (!$ok) {
				return false;
			}
			$iddetalle = (int)dbValue(
				"SELECT iddetalle FROM conteo_detalle WHERE idconteo=? AND idarticulo=? AND idvariante=? AND idlote=? AND lote_clave=?",
				array($idconteo, $idarticulo, $idvariante, $idlote, $loteClave),
				0
			);
			return array('iddetalle' => $iddetalle);
		});
		if ($res === false) {
			return array('ok' => false, 'message' => $error !== '' ? $error : 'No se pudo registrar la lectura.');
		}
		$linea = $this->lineas($idconteo, (int)$res['iddetalle']);
		return array('ok' => true, 'message' => 'Registrado', 'linea' => $linea ? $linea[0] : null);
	}

	/** Stock del sistema para una linea (talla, lote, lote nuevo = 0, sin lote o total). */
	private function stockActualLinea($idarticulo, $idvariante, $idlote, $loteClave, $porLote, $idalmacen)
	{
		if ($idvariante > 0) {
			return Stock::enAlmacen($idalmacen, $idarticulo, $idvariante);
		}
		if ($idlote > 0) {
			return round((float)dbValue("SELECT stock FROM lote WHERE idlote=?", array($idlote), 0), 3);
		}
		if ($loteClave !== '') {
			return 0.0;
		}
		$stock = Stock::enAlmacen($idalmacen, $idarticulo);
		return $porLote ? max(0, round($stock - Inventario::stockEnLotes($idarticulo, $idalmacen), 3)) : $stock;
	}

	public function eliminarLinea($idconteo, $iddetalle)
	{
		$c = dbRow("SELECT estado FROM conteo_inventario WHERE idconteo=?", array((int)$idconteo));
		if (!$c || $c['estado'] !== 'ABIERTO') {
			return array('ok' => false, 'message' => 'El conteo ya no está abierto.');
		}
		dbExec("DELETE FROM conteo_detalle WHERE idconteo=? AND iddetalle=?", array((int)$idconteo, (int)$iddetalle));
		return dbAfectadas() > 0
			? array('ok' => true, 'message' => 'Línea quitada del conteo.')
			: array('ok' => false, 'message' => 'La línea no existe.');
	}

	/** Lineas del conteo (o una sola), la ultima lectura primero. */
	public function lineas($idconteo, $iddetalle = 0)
	{
		$filas = dbAll(
			"SELECT d.iddetalle, d.idarticulo, d.idvariante, d.idlote, d.lote_clave, d.cantidad, d.stock_sistema, d.costo_unitario, d.lecturas,
				d.actualizado, d.diferencia_aplicada, d.idajuste,
				a.nombre, IFNULL(um.abreviatura,'und') AS unidad, IFNULL(um.permite_fraccion,0) AS permite_fraccion,
				COALESCE(av.codigo, a.codigo, '') AS codigo, IFNULL(av.talla,'') AS talla, IFNULL(av.color,'') AS color,
				COALESCE(l.codigo_lote, d.lote_codigo) AS lote_codigo, COALESCE(l.fecha_vencimiento, d.lote_vencimiento) AS lote_vencimiento,
				u.nombre AS usuario
			 FROM conteo_detalle d
			 INNER JOIN articulo a ON a.idarticulo=d.idarticulo
			 LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
			 LEFT JOIN articulo_variante av ON av.idvariante=d.idvariante AND d.idvariante>0
			 LEFT JOIN lote l ON l.idlote=d.idlote AND d.idlote>0
			 INNER JOIN usuario u ON u.idusuario=d.idusuario
			 WHERE d.idconteo=?" . ((int)$iddetalle > 0 ? " AND d.iddetalle=?" : "") . "
			 ORDER BY d.actualizado DESC, d.iddetalle DESC",
			(int)$iddetalle > 0 ? array((int)$idconteo, (int)$iddetalle) : array((int)$idconteo)
		);
		$conteo = dbRow("SELECT por_lote FROM conteo_inventario WHERE idconteo=?", array((int)$idconteo));
		$porLote = self::porLote($conteo);
		foreach ($filas as &$f) {
			$f['nombre'] = html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8');
			$f['variante'] = html_entity_decode(Variante::etiqueta($f['talla'], $f['color']), ENT_QUOTES, 'UTF-8');
			$f['cantidad'] = round((float)$f['cantidad'], 3);
			$f['stock_sistema'] = round((float)$f['stock_sistema'], 3);
			$f['diferencia'] = round($f['cantidad'] - $f['stock_sistema'], 3);
			$f['valor'] = round($f['diferencia'] * (float)$f['costo_unitario'], 2);
			$f['tipo_lote'] = (int)$f['idlote'] > 0 ? 'lote' : ($f['lote_clave'] !== '' ? 'nuevo' : (($porLote && (int)$f['idvariante'] === 0) ? 'sin_lote' : ''));
			unset($f['talla'], $f['color']);
		}
		unset($f);
		return $filas;
	}

	/** Articulos del alcance con stock que aun no tienen ninguna lectura. */
	public function pendientes($conteo, $limite = 1000)
	{
		$alm = self::almacen($conteo);
		$params = array($alm, (int)$conteo['idconteo']);
		$filtroCat = '';
		if (!empty($conteo['idcategoria'])) {
			$filtroCat = ' AND a.idcategoria=?';
			$params[] = (int)$conteo['idcategoria'];
		}
		$filas = dbAll(
			"SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, sa.stock, a.precio_compra, IFNULL(um.abreviatura,'und') AS unidad, IFNULL(cat.nombre,'') AS categoria
			 FROM articulo a
			 INNER JOIN (SELECT idarticulo, SUM(stock) AS stock FROM stock_almacen WHERE idalmacen=? GROUP BY idarticulo) sa ON sa.idarticulo=a.idarticulo
			 LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
			 LEFT JOIN categoria cat ON cat.idcategoria=a.idcategoria
			 WHERE a.condicion=1 AND sa.stock>0
			   AND NOT EXISTS (SELECT 1 FROM conteo_detalle d WHERE d.idconteo=? AND d.idarticulo=a.idarticulo)" . $filtroCat . "
			 ORDER BY cat.nombre ASC, a.nombre ASC
			 LIMIT " . (int)$limite,
			$params
		);
		foreach ($filas as &$f) {
			$f['nombre'] = html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8');
			$f['categoria'] = html_entity_decode($f['categoria'], ENT_QUOTES, 'UTF-8');
			$f['stock'] = round((float)$f['stock'], 3);
			$f['valor'] = round($f['stock'] * (float)$f['precio_compra'], 2);
		}
		unset($f);
		return $filas;
	}

	/** Totales para las tarjetas de la pantalla. */
	public function resumen($conteo)
	{
		$params = array(self::almacen($conteo));
		$filtroCat = '';
		if (!empty($conteo['idcategoria'])) {
			$filtroCat = ' AND a.idcategoria=?';
			$params[] = (int)$conteo['idcategoria'];
		}
		$alcance = dbRow(
			"SELECT COUNT(*) AS articulos, SUM(CASE WHEN IFNULL(sa.stock,0)>0 THEN 1 ELSE 0 END) AS con_stock
			 FROM articulo a LEFT JOIN (SELECT idarticulo, SUM(stock) AS stock FROM stock_almacen WHERE idalmacen=? GROUP BY idarticulo) sa ON sa.idarticulo=a.idarticulo
			 WHERE a.condicion=1" . $filtroCat,
			$params
		);
		$lineas = $this->lineas($conteo['idconteo']);
		$contados = array();
		$sobrante = 0.0;
		$faltante = 0.0;
		$conDif = 0;
		$unidades = 0.0;
		foreach ($lineas as $l) {
			$contados[(int)$l['idarticulo']] = true;
			$unidades += $l['cantidad'];
			if (abs($l['diferencia']) >= 0.0005) {
				$conDif++;
				if ($l['valor'] > 0) {
					$sobrante += $l['valor'];
				} else {
					$faltante += -$l['valor'];
				}
			}
		}
		$pend = $this->pendientes($conteo);
		$valorPend = 0.0;
		foreach ($pend as $p) {
			$valorPend += $p['valor'];
		}
		return array(
			'articulos_alcance' => (int)($alcance ? $alcance['articulos'] : 0),
			'articulos_con_stock' => (int)($alcance ? $alcance['con_stock'] : 0),
			'articulos_contados' => count($contados),
			'lineas' => count($lineas),
			'unidades' => round($unidades, 3),
			'con_diferencia' => $conDif,
			'valor_sobrante' => round($sobrante, 2),
			'valor_faltante' => round($faltante, 2),
			'pendientes' => count($pend),
			'valor_pendientes' => round($valorPend, 2)
		);
	}

	/**
	 * Ajustes que generaria aplicar el conteo, con el stock actual.
	 * $cero = true: lo del alcance que no se conto (articulos, tallas, lotes y
	 * stock sin lote) se pone en cero.
	 * Cada movimiento: idarticulo, idvariante, iddetalle (0 = no contado), tipo,
	 * cantidad, costo, lote (spec de Inventario::moverStock), nombre, detalle, valor, nota.
	 */
	public function plan($conteo, $cero)
	{
		$porLoteConteo = self::porLote($conteo);
		$alm = self::almacen($conteo);
		Stock::cuadrarDescuadrados();
		$movs = array();
		$contadoArt = array();
		$contadoVar = array();
		$contadoLote = array();
		$contadoSinLote = array();

		foreach ($this->lineas($conteo['idconteo']) as $l) {
			$idart = (int)$l['idarticulo'];
			$contadoArt[$idart] = true;
			if ((int)$l['idvariante'] > 0) {
				$contadoVar[(int)$l['idvariante']] = true;
			}
			if ((int)$l['idlote'] > 0) {
				$contadoLote[(int)$l['idlote']] = true;
			}
			if ($l['tipo_lote'] === 'sin_lote') {
				$contadoSinLote[$idart] = true;
			}
			$dif = $l['diferencia'];
			if (abs($dif) < 0.0005) {
				continue;
			}
			$tipo = $dif > 0 ? 'ENTRADA' : 'SALIDA';
			$cantidad = abs($dif);
			$nota = '';
			if ($l['tipo_lote'] === 'lote') {
				$lote = array('modo' => 'lote', 'idlote' => (int)$l['idlote']);
				$disponible = (float)dbValue("SELECT stock FROM lote WHERE idlote=?", array((int)$l['idlote']), 0);
			} elseif ($l['tipo_lote'] === 'nuevo') {
				$lote = array('modo' => 'nuevo', 'codigo' => (string)$l['lote_codigo'], 'vence' => (string)$l['lote_vencimiento']);
				$disponible = 0;
			} elseif ($l['tipo_lote'] === 'sin_lote') {
				$lote = $tipo === 'SALIDA' ? array('modo' => 'sin_lote') : array('modo' => 'ninguno');
				$disponible = max(0, Stock::enAlmacen($alm, $idart) - Inventario::stockEnLotes($idart, $alm));
			} else {
				$lote = $tipo === 'SALIDA' ? array('modo' => 'auto') : array('modo' => 'ninguno');
				$disponible = (int)$l['idvariante'] > 0
					? Stock::enAlmacen($alm, $idart, (int)$l['idvariante'])
					: Stock::enAlmacen($alm, $idart);
			}
			// Se vendio mas de lo que falta desde que se conto: no se puede retirar lo que ya no esta
			if ($tipo === 'SALIDA' && $disponible + 0.0005 < $cantidad) {
				$nota = 'Solo quedan ' . formatearCantidad(max(0, $disponible)) . ' en el sistema';
				$cantidad = round(max(0, $disponible), 3);
				if ($cantidad <= 0) {
					continue;
				}
			}
			$movs[] = array(
				'idarticulo' => $idart, 'idvariante' => (int)$l['idvariante'], 'iddetalle' => (int)$l['iddetalle'],
				'tipo' => $tipo, 'cantidad' => round($cantidad, 3), 'costo' => (float)$l['costo_unitario'], 'lote' => $lote,
				'nombre' => $l['nombre'], 'detalle' => $this->detalleLinea($l), 'unidad' => $l['unidad'],
				'valor' => round($cantidad * (float)$l['costo_unitario'] * ($tipo === 'ENTRADA' ? 1 : -1), 2), 'nota' => $nota
			);
		}

		if ($cero) {
			$params = array($alm);
			$filtroCat = '';
			if (!empty($conteo['idcategoria'])) {
				$filtroCat = ' AND a.idcategoria=?';
				$params[] = (int)$conteo['idcategoria'];
			}
			// Lo que tiene stock en el almacen del conteo
			$articulos = dbAll(
				"SELECT a.idarticulo, a.nombre, sa.stock, a.precio_compra, IFNULL(um.abreviatura,'und') AS unidad
				 FROM articulo a
				 INNER JOIN (SELECT idarticulo, SUM(stock) AS stock FROM stock_almacen WHERE idalmacen=? GROUP BY idarticulo) sa ON sa.idarticulo=a.idarticulo
				 LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
				 WHERE a.condicion=1 AND sa.stock>0" . $filtroCat . " ORDER BY a.nombre",
				$params
			);
			foreach ($articulos as $a) {
				$idart = (int)$a['idarticulo'];
				$nombre = html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8');
				$costoArt = (float)$a['precio_compra'];
				$base = array('idarticulo' => $idart, 'iddetalle' => 0, 'tipo' => 'SALIDA', 'nombre' => $nombre, 'unidad' => $a['unidad'], 'nota' => 'No se contó');
				$variantes = Variante::deArticulo($idart);
				if ($variantes) {
					foreach ($variantes as $v) {
						$stV = Stock::enAlmacen($alm, $idart, (int)$v['idvariante']);
						if ($stV > 0 && empty($contadoVar[(int)$v['idvariante']])) {
							$movs[] = $base + array('idvariante' => (int)$v['idvariante'], 'cantidad' => round($stV, 3), 'costo' => $costoArt,
								'lote' => array('modo' => 'auto'), 'detalle' => html_entity_decode(Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'),
								'valor' => -round($stV * $costoArt, 2));
						}
					}
					continue;
				}
				if ($porLoteConteo) {
					$enLotes = 0.0;
					foreach (Lote::deArticulo($idart, $alm) as $lt) {
						$enLotes += (float)$lt['stock'];
						if (empty($contadoLote[(int)$lt['idlote']])) {
							$costo = (float)$lt['costo_unitario'] > 0 ? (float)$lt['costo_unitario'] : $costoArt;
							$movs[] = $base + array('idvariante' => 0, 'cantidad' => round((float)$lt['stock'], 3), 'costo' => $costo,
								'lote' => array('modo' => 'lote', 'idlote' => (int)$lt['idlote']), 'detalle' => $this->textoLote($lt['codigo_lote'], $lt['fecha_vencimiento']),
								'valor' => -round((float)$lt['stock'] * $costo, 2));
						}
					}
					$sinLote = round((float)$a['stock'] - $enLotes, 3);
					if ($sinLote > 0 && empty($contadoSinLote[$idart])) {
						$movs[] = $base + array('idvariante' => 0, 'cantidad' => $sinLote, 'costo' => $costoArt, 'lote' => array('modo' => 'sin_lote'),
							'detalle' => 'Sin lote', 'valor' => -round($sinLote * $costoArt, 2));
					}
					continue;
				}
				if (empty($contadoArt[$idart])) {
					$movs[] = $base + array('idvariante' => 0, 'cantidad' => round((float)$a['stock'], 3), 'costo' => $costoArt, 'lote' => array('modo' => 'auto'),
						'detalle' => '', 'valor' => -round((float)$a['stock'] * $costoArt, 2));
				}
			}
		}

		// Por articulo, las entradas antes que las salidas (el stock nunca pasa por negativo)
		usort($movs, function ($x, $y) {
			if ($x['idarticulo'] !== $y['idarticulo']) {
				return $x['idarticulo'] - $y['idarticulo'];
			}
			return strcmp($x['tipo'], $y['tipo']);
		});
		return $movs;
	}

	private function textoLote($codigo, $vence)
	{
		$txt = trim((string)$codigo) !== '' ? 'Lote ' . $codigo : 'Lote sin código';
		return $txt . ($vence ? ' · vence ' . date('d/m/Y', strtotime($vence)) : '');
	}

	private function detalleLinea($l)
	{
		if ($l['variante'] !== '') {
			return $l['variante'];
		}
		if ($l['tipo_lote'] === 'lote' || $l['tipo_lote'] === 'nuevo') {
			return $this->textoLote($l['lote_codigo'], $l['lote_vencimiento']) . ($l['tipo_lote'] === 'nuevo' ? ' (nuevo)' : '');
		}
		return $l['tipo_lote'] === 'sin_lote' ? 'Sin lote' : '';
	}

	/**
	 * Aplica el conteo: genera los ajustes y lo cierra. Todo o nada.
	 * Devuelve array(ok, message, ajustes, valor_sobrante, valor_faltante).
	 */
	public function aplicar($idconteo, $idusuario, $cero)
	{
		$idconteo = (int)$idconteo;
		$error = '';
		$res = dbTransaccion(function () use ($idconteo, $idusuario, $cero, &$error) {
			$conteo = dbRow("SELECT * FROM conteo_inventario WHERE idconteo=? FOR UPDATE", array($idconteo));
			if (!$conteo || $conteo['estado'] !== 'ABIERTO') {
				$error = 'El conteo ya no está abierto.';
				return false;
			}
			if (!$cero && (int)dbValue("SELECT COUNT(*) FROM conteo_detalle WHERE idconteo=?", array($idconteo), 0) === 0) {
				$error = 'El conteo no tiene ninguna lectura.';
				return false;
			}
			$sobrante = 0.0;
			$faltante = 0.0;
			$ajustes = 0;
			$obs = 'Conteo #' . $idconteo . ': ' . $conteo['nombre'];
			foreach ($this->plan($conteo, $cero) as $m) {
				$r = Inventario::moverStock(array(
					'idarticulo' => $m['idarticulo'], 'idvariante' => $m['idvariante'] > 0 ? $m['idvariante'] : null,
					'idusuario' => (int)$idusuario, 'tipo' => $m['tipo'], 'motivo' => 'CONTEO', 'cantidad' => $m['cantidad'],
					'costo' => $m['costo'], 'observacion' => $obs . ($m['iddetalle'] ? '' : ' (no contado)'), 'lote' => $m['lote'], 'idconteo' => $idconteo,
					'idalmacen' => self::almacen($conteo)
				), $error);
				if ($r === false) {
					if ($error === '') {
						$error = 'No se pudo ajustar ' . $m['nombre'] . '.';
					}
					return false;
				}
				$ajustes++;
				if ($m['valor'] > 0) {
					$sobrante += $m['valor'];
				} else {
					$faltante += -$m['valor'];
				}
				if ($m['iddetalle'] > 0) {
					dbExec(
						"UPDATE conteo_detalle SET diferencia_aplicada=?, idajuste=? WHERE iddetalle=?",
						array($m['tipo'] === 'ENTRADA' ? $m['cantidad'] : -$m['cantidad'], (int)$r['idajuste'], $m['iddetalle'])
					);
				}
			}
			dbExec("UPDATE conteo_detalle SET diferencia_aplicada=0 WHERE idconteo=? AND diferencia_aplicada IS NULL", array($idconteo));
			$ok = dbExec(
				"UPDATE conteo_inventario SET estado='APLICADO', idusuario_cierre=?, fecha_cierre=NOW(), ajustes=?, valor_sobrante=?, valor_faltante=? WHERE idconteo=?",
				array((int)$idusuario, $ajustes, round($sobrante, 2), round($faltante, 2), $idconteo)
			);
			if (!$ok) {
				return false;
			}
			return array('ajustes' => $ajustes, 'valor_sobrante' => round($sobrante, 2), 'valor_faltante' => round($faltante, 2), 'nombre' => $conteo['nombre']);
		});
		if ($res === false) {
			return array('ok' => false, 'message' => $error !== '' ? $error : 'No se pudo aplicar el conteo.');
		}
		return array(
			'ok' => true,
			'message' => $res['ajustes'] > 0 ? 'Conteo aplicado: ' . $res['ajustes'] . ' ajuste(s) de inventario.' : 'Conteo cerrado: el stock coincidía, no hubo ajustes.',
			'ajustes' => $res['ajustes'], 'valor_sobrante' => $res['valor_sobrante'], 'valor_faltante' => $res['valor_faltante'], 'nombre' => $res['nombre']
		);
	}

	public function anular($idconteo, $idusuario)
	{
		dbExec("UPDATE conteo_inventario SET estado='ANULADO', idusuario_cierre=?, fecha_cierre=NOW() WHERE idconteo=? AND estado='ABIERTO'", array((int)$idusuario, (int)$idconteo));
		return dbAfectadas() > 0
			? array('ok' => true, 'message' => 'Conteo anulado. El stock no se modificó.')
			: array('ok' => false, 'message' => 'El conteo ya no está abierto.');
	}

	/** Busqueda por nombre o codigo dentro del alcance (cuando el codigo no se reconoce). */
	public function buscar($conteo, $q)
	{
		$q = trim((string)$q);
		if (mb_strlen($q, 'UTF-8') < 2) {
			return array();
		}
		$params = array('%' . $q . '%', $q . '%', $q . '%');
		$filtroCat = '';
		if (!empty($conteo['idcategoria'])) {
			$filtroCat = ' AND a.idcategoria=?';
			$params[] = (int)$conteo['idcategoria'];
		}
		$filas = dbAll(
			"SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, a.stock, IFNULL(um.abreviatura,'und') AS unidad
			 FROM articulo a LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
			 WHERE a.condicion=1 AND (a.nombre LIKE ? OR a.codigo LIKE ?
				OR EXISTS (SELECT 1 FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1 AND v.codigo LIKE ?))" . $filtroCat . "
			 ORDER BY a.nombre LIMIT 12",
			$params
		);
		foreach ($filas as &$f) {
			$f['nombre'] = html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8');
			$f['stock'] = round((float)$f['stock'], 3);
		}
		unset($f);
		return $filas;
	}
}
