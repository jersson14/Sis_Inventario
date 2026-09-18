<?php
/**
 * Importacion masiva desde Excel (.xlsx) o CSV: articulos, clientes y proveedores.
 * Sin librerias externas: el .xlsx se lee con ZipArchive + SimpleXML.
 */
require_once "../config/Conexion.php";
require_once "../modelos/Stock.php";
require_once "../config/negocio.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";

class Importacion
{
	const MAX_FILAS = 5000;

	/** Columnas por tipo: clave normalizada => [etiqueta, obligatoria, ejemplo] */
	public static function columnas($tipo)
	{
		if ($tipo === 'articulos') {
			return array(
				'nombre'        => array('Nombre', true, 'Perno hexagonal 1/2" x 2"'),
				'codigo'        => array('Código', false, 'FER-100001'),
				'categoria'     => array('Categoría', false, 'Ferretería'),
				'unidad'        => array('Unidad', false, 'und'),
				'stock'         => array('Stock', false, '100'),
				'stock_minimo'  => array('Stock mínimo', false, '10'),
				'precio_compra' => array('Precio compra', false, '0.80'),
				'precio_venta'  => array('Precio venta', false, '1.50'),
				'descripcion'   => array('Descripción', false, 'Acero zincado'),
			) + (Variante::activo() ? array(
				// Rubro ropa: una fila por talla/color; el codigo de esa fila es el de la combinacion
				'talla'         => array('Talla', false, 'M'),
				'color'         => array('Color', false, 'Negro'),
			) : array());
		}
		return array(
			'nombre'         => array('Nombre', true, $tipo === 'proveedores' ? 'Distribuidora Ferretera SAC' : 'María Quispe'),
			'tipo_documento' => array('Tipo documento', false, $tipo === 'proveedores' ? 'RUC' : 'DNI'),
			'num_documento'  => array('N° documento', false, $tipo === 'proveedores' ? '20601234567' : '45678912'),
			'direccion'      => array('Dirección', false, 'Av. Principal 123'),
			'telefono'       => array('Teléfono', false, '987654321'),
			'email'          => array('Email', false, 'correo@dominio.com'),
		);
	}

	/** Normaliza un VALOR (sin alias de columnas): minusculas, sin acentos, guiones bajos. */
	public static function normalizarValor($texto)
	{
		$t = mb_strtolower(trim((string)$texto), 'UTF-8');
		$t = strtr($t, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', '°' => '', 'º' => ''));
		return trim(preg_replace('/[^a-z0-9]+/', '_', $t), '_');
	}

	public static function normalizarClave($texto)
	{
		$t = mb_strtolower(trim((string)$texto), 'UTF-8');
		$t = strtr($t, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', '°' => '', 'º' => ''));
		$t = preg_replace('/[^a-z0-9]+/', '_', $t);
		$t = trim($t, '_');
		$alias = array(
			'articulo' => 'nombre', 'producto' => 'nombre', 'nombre_del_articulo' => 'nombre', 'razon_social' => 'nombre', 'cliente' => 'nombre', 'proveedor' => 'nombre',
			'cod' => 'codigo', 'codigo_de_barras' => 'codigo', 'barcode' => 'codigo', 'sku' => 'codigo',
			'cat' => 'categoria', 'linea' => 'categoria', 'familia' => 'categoria',
			'unidad_de_medida' => 'unidad', 'um' => 'unidad', 'medida' => 'unidad',
			'cantidad' => 'stock', 'existencia' => 'stock', 'stock_actual' => 'stock',
			'minimo' => 'stock_minimo', 'stock_min' => 'stock_minimo',
			'costo' => 'precio_compra', 'precio_de_compra' => 'precio_compra', 'p_compra' => 'precio_compra', 'pcompra' => 'precio_compra',
			'precio' => 'precio_venta', 'precio_de_venta' => 'precio_venta', 'p_venta' => 'precio_venta', 'pventa' => 'precio_venta', 'pvp' => 'precio_venta',
			'desc' => 'descripcion', 'detalle' => 'descripcion', 'observacion' => 'descripcion',
			'size' => 'talla', 'tamano' => 'talla', 'talle' => 'talla', 'colour' => 'color',
			'tipo_doc' => 'tipo_documento', 'tipodocumento' => 'tipo_documento', 'documento' => 'num_documento', 'n_documento' => 'num_documento', 'numero_documento' => 'num_documento', 'dni' => 'num_documento', 'ruc' => 'num_documento', 'nro_documento' => 'num_documento',
			'celular' => 'telefono', 'fono' => 'telefono', 'whatsapp' => 'telefono', 'correo' => 'email', 'e_mail' => 'email', 'mail' => 'email', 'direccion_fiscal' => 'direccion',
		);
		return isset($alias[$t]) ? $alias[$t] : $t;
	}

	// ------------------------------------------------------------------
	// Lectura de archivos
	// ------------------------------------------------------------------

	/** Devuelve array('ok'=>bool, 'filas'=>[[assoc]], 'cabeceras'=>[], 'message'=>) */
	public function leer($ruta, $nombreOriginal)
	{
		$ext = strtolower(pathinfo((string)$nombreOriginal, PATHINFO_EXTENSION));
		if ($ext === 'xlsx') {
			$matriz = $this->leerXlsx($ruta);
		} elseif (in_array($ext, array('csv', 'txt'), true)) {
			$matriz = $this->leerCsv($ruta);
		} elseif ($ext === 'xls') {
			return array('ok' => false, 'message' => 'El formato .xls (Excel 97-2003) no es compatible. Guarda el archivo como .xlsx o .csv.');
		} else {
			return array('ok' => false, 'message' => 'Formato no compatible. Usa .xlsx o .csv.');
		}
		if (!is_array($matriz)) {
			return array('ok' => false, 'message' => is_string($matriz) ? $matriz : 'No se pudo leer el archivo.');
		}
		// Quitar filas totalmente vacias
		$matriz = array_values(array_filter($matriz, function ($f) { foreach ($f as $c) { if (trim((string)$c) !== '') return true; } return false; }));
		if (count($matriz) < 2) {
			return array('ok' => false, 'message' => 'El archivo no tiene datos (se esperaba una fila de cabeceras y al menos una de datos).');
		}
		$cab = array();
		foreach ($matriz[0] as $c) { $cab[] = self::normalizarClave($c); }
		$filas = array();
		for ($i = 1; $i < count($matriz) && count($filas) < self::MAX_FILAS; $i++) {
			$f = array();
			foreach ($cab as $j => $k) {
				if ($k === '') continue;
				$f[$k] = isset($matriz[$i][$j]) ? trim((string)$matriz[$i][$j]) : '';
			}
			$f['_fila'] = $i + 1;
			$filas[] = $f;
		}
		return array('ok' => true, 'filas' => $filas, 'cabeceras' => $cab, 'truncado' => count($matriz) - 1 > self::MAX_FILAS);
	}

	private function leerCsv($ruta)
	{
		$contenido = file_get_contents($ruta);
		if ($contenido === false) return 'No se pudo abrir el archivo.';
		if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") $contenido = substr($contenido, 3);
		if (!mb_check_encoding($contenido, 'UTF-8')) $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
		$primera = strtok($contenido, "\r\n");
		$delims = array(';' => substr_count($primera, ';'), ',' => substr_count($primera, ','), "\t" => substr_count($primera, "\t"), '|' => substr_count($primera, '|'));
		arsort($delims);
		$delim = key($delims);
		if ($delims[$delim] === 0) $delim = ';';
		$out = array();
		$h = fopen('php://temp', 'r+');
		fwrite($h, $contenido);
		rewind($h);
		while (($row = fgetcsv($h, 0, $delim, '"', '\\')) !== false) { $out[] = $row; }
		fclose($h);
		return $out;
	}

	private function leerXlsx($ruta)
	{
		if (!class_exists('ZipArchive')) return 'El servidor no tiene la extensión zip habilitada para leer .xlsx. Usa CSV.';
		$zip = new ZipArchive();
		if ($zip->open($ruta) !== true) return 'No se pudo abrir el archivo .xlsx.';
		$shared = array();
		$xmlShared = $zip->getFromName('xl/sharedStrings.xml');
		if ($xmlShared !== false) {
			$sx = @simplexml_load_string($xmlShared);
			if ($sx) {
				foreach ($sx->si as $si) {
					$texto = '';
					if (isset($si->t)) { $texto = (string)$si->t; }
					elseif (isset($si->r)) { foreach ($si->r as $r) { $texto .= (string)$r->t; } }
					$shared[] = $texto;
				}
			}
		}
		// Primera hoja: resolver via workbook rels
		$hoja = 'xl/worksheets/sheet1.xml';
		$wb = $zip->getFromName('xl/workbook.xml');
		$rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
		if ($wb !== false && $rels !== false) {
			$sxw = @simplexml_load_string($wb);
			$sxr = @simplexml_load_string($rels);
			if ($sxw && $sxr && isset($sxw->sheets->sheet[0])) {
				$attrs = $sxw->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
				$rid = isset($attrs['id']) ? (string)$attrs['id'] : '';
				foreach ($sxr->Relationship as $rel) {
					if ((string)$rel['Id'] === $rid) { $t = (string)$rel['Target']; $hoja = (strpos($t, '/') === 0) ? ltrim($t, '/') : 'xl/' . $t; break; }
				}
			}
		}
		$xml = $zip->getFromName($hoja);
		$zip->close();
		if ($xml === false) return 'El archivo .xlsx no contiene hojas legibles.';
		$sx = @simplexml_load_string($xml);
		if (!$sx || !isset($sx->sheetData)) return 'No se pudo interpretar la hoja de cálculo.';
		$out = array();
		foreach ($sx->sheetData->row as $row) {
			$fila = array();
			foreach ($row->c as $c) {
				$ref = (string)$c['r'];
				$col = self::columnaIndice(preg_replace('/\d+/', '', $ref));
				$t = (string)$c['t'];
				$v = '';
				if ($t === 's') { $idx = (int)$c->v; $v = isset($shared[$idx]) ? $shared[$idx] : ''; }
				elseif ($t === 'inlineStr') { $v = isset($c->is->t) ? (string)$c->is->t : ''; }
				elseif ($t === 'b') { $v = ((string)$c->v === '1') ? '1' : '0'; }
				else { $v = isset($c->v) ? (string)$c->v : ''; }
				// Numeros en notacion cientifica de Excel
				if ($v !== '' && is_numeric($v) && stripos($v, 'e') !== false) { $v = rtrim(rtrim(sprintf('%.6F', (float)$v), '0'), '.'); }
				$fila[$col] = $v;
			}
			if (!$fila) continue;
			$max = max(array_keys($fila));
			$lin = array();
			for ($i = 0; $i <= $max; $i++) { $lin[] = isset($fila[$i]) ? $fila[$i] : ''; }
			$out[] = $lin;
		}
		return $out;
	}

	private static function columnaIndice($letras)
	{
		$n = 0;
		$letras = strtoupper($letras);
		for ($i = 0; $i < strlen($letras); $i++) { $n = $n * 26 + (ord($letras[$i]) - 64); }
		return max(0, $n - 1);
	}

	// ------------------------------------------------------------------
	// Validacion
	// ------------------------------------------------------------------

	private function numero($v, $default = 0)
	{
		$v = str_replace(array(' ', 'S/', '$'), '', (string)$v);
		if (substr_count($v, ',') === 1 && substr_count($v, '.') === 0) $v = str_replace(',', '.', $v);
		$v = str_replace(',', '', $v);
		return is_numeric($v) ? (float)$v : $default;
	}

	public function validarArticulos(array $filas)
	{
		// Rubro ropa: las filas con talla o color se validan como combinaciones de un articulo
		if (Variante::activo()) {
			$simples = array();
			$conVariante = array();
			foreach ($filas as $f) {
				if ((isset($f['talla']) && trim($f['talla']) !== '') || (isset($f['color']) && trim($f['color']) !== '')) {
					$conVariante[] = $f;
				} else {
					$simples[] = $f;
				}
			}
			if ($conVariante) {
				$a = $simples ? $this->validarArticulosSimples($simples) : array('crear' => 0, 'actualizar' => 0, 'error' => 0, 'categorias_nuevas' => array(), 'filas' => array());
				$b = $this->validarArticulosVariantes($conVariante);
				$res = array(
					'crear' => $a['crear'] + $b['crear'], 'actualizar' => $a['actualizar'] + $b['actualizar'], 'error' => $a['error'] + $b['error'],
					'categorias_nuevas' => array_values(array_unique(array_merge($a['categorias_nuevas'], $b['categorias_nuevas']))),
					'filas' => array_merge($a['filas'], $b['filas']),
					'modo_variantes' => true
				);
				usort($res['filas'], function ($x, $y) { return $x['fila'] <=> $y['fila']; });
				return $res;
			}
		}
		return $this->validarArticulosSimples($filas);
	}

	/**
	 * Filas con talla/color: cada una es una combinacion del articulo "nombre".
	 * Varias filas del mismo nombre forman un articulo con varias tallas/colores.
	 */
	private function validarArticulosVariantes(array $filas)
	{
		$cats = array(); foreach (dbAll("SELECT idcategoria, nombre FROM categoria") as $c) { $cats[self::normalizarValor($c['nombre'])] = (int)$c['idcategoria']; }
		$unis = array(); foreach (dbAll("SELECT idunidad, nombre, abreviatura FROM unidad_medida WHERE condicion=1") as $u) { $unis[self::normalizarValor($u['abreviatura'])] = (int)$u['idunidad']; $unis[self::normalizarValor($u['nombre'])] = (int)$u['idunidad']; }
		$porNombre = array();
		foreach (dbAll("SELECT a.idarticulo, a.nombre, a.stock, (SELECT COUNT(*) FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1) AS variantes FROM articulo a") as $a) {
			$porNombre[mb_strtolower(html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8'))] = $a;
		}
		// Categoria por articulo: basta con que una de sus filas la indique
		$catDeNombre = array();
		foreach ($filas as $f) {
			$k = mb_strtolower(isset($f['nombre']) ? trim($f['nombre']) : '');
			if ($k !== '' && !isset($catDeNombre[$k]) && isset($f['categoria']) && trim($f['categoria']) !== '') { $catDeNombre[$k] = trim($f['categoria']); }
		}
		$vistos = array();
		$vistosCodigo = array();
		$res = array('crear' => 0, 'actualizar' => 0, 'error' => 0, 'categorias_nuevas' => array(), 'filas' => array());
		foreach ($filas as $f) {
			$errores = array();
			$nombre = isset($f['nombre']) ? trim($f['nombre']) : '';
			$talla = Variante::textoCorto(isset($f['talla']) ? $f['talla'] : '', 20);
			$color = Variante::textoCorto(isset($f['color']) ? $f['color'] : '', 30);
			$codigo = isset($f['codigo']) ? trim($f['codigo']) : '';
			$claveNombre = mb_strtolower($nombre);
			if ($nombre === '') $errores[] = 'Nombre vacío';
			if (mb_strlen($nombre) > 100) $errores[] = 'Nombre supera 100 caracteres';
			if (mb_strlen($codigo) > 50) $errores[] = 'Código supera 50 caracteres';
			$clave = $claveNombre . '|' . mb_strtolower($talla) . '|' . mb_strtolower($color);
			if (isset($vistos[$clave])) $errores[] = 'Talla/color repetida en el archivo (fila ' . $vistos[$clave] . ')';
			$vistos[$clave] = $f['_fila'];
			if ($codigo !== '') {
				if (isset($vistosCodigo[mb_strtolower($codigo)])) $errores[] = 'Código repetido en el archivo (fila ' . $vistosCodigo[mb_strtolower($codigo)] . ')';
				$vistosCodigo[mb_strtolower($codigo)] = $f['_fila'];
			}
			$categoria = isset($catDeNombre[$claveNombre]) ? $catDeNombre[$claveNombre] : '';
			$idcat = 0; $catNueva = false;
			if ($categoria !== '') {
				$k = self::normalizarValor($categoria);
				if (isset($cats[$k])) $idcat = $cats[$k]; else { $catNueva = true; $res['categorias_nuevas'][$k] = $categoria; }
			}
			$unidad = isset($f['unidad']) ? trim($f['unidad']) : '';
			$idunidad = 0;
			if ($unidad !== '') { $k = self::normalizarValor($unidad); if (isset($unis[$k])) $idunidad = $unis[$k]; else $errores[] = 'Unidad "' . $unidad . '" no existe'; }
			if ($idunidad === 0 && $unidad === '') { $idunidad = isset($unis['und']) ? $unis['und'] : (count($unis) ? reset($unis) : 0); }
			$stock = $this->numero(isset($f['stock']) ? $f['stock'] : '', 0);
			$stockMin = $this->numero(isset($f['stock_minimo']) ? $f['stock_minimo'] : '', 0);
			$pc = $this->numero(isset($f['precio_compra']) ? $f['precio_compra'] : '', 0);
			$pv = $this->numero(isset($f['precio_venta']) ? $f['precio_venta'] : '', 0);
			if ($stock < 0 || $stockMin < 0 || $pc < 0 || $pv < 0) $errores[] = 'Valores negativos';

			$art = isset($porNombre[$claveNombre]) ? $porNombre[$claveNombre] : null;
			$variante = null;
			if ($art) {
				if ((int)$art['variantes'] === 0 && (float)$art['stock'] > 0) {
					$errores[] = 'El artículo ya tiene ' . formatearCantidad($art['stock']) . ' en stock sin talla/color: reparte ese stock desde su ficha antes de importar combinaciones';
				}
				// Se guardan escapados (limpiarCadena), igual que desde el formulario
				$variante = dbRow("SELECT idvariante, stock FROM articulo_variante WHERE idarticulo=? AND talla=? AND color=? LIMIT 1", array((int)$art['idarticulo'], limpiarCadena($talla), limpiarCadena($color)));
			}
			if ($codigo !== '') {
				$usado = (int)dbValue(
					"SELECT (SELECT COUNT(*) FROM articulo WHERE codigo=?)
					      + (SELECT COUNT(*) FROM articulo_presentacion WHERE codigo=? AND condicion=1)
					      + (SELECT COUNT(*) FROM articulo_variante WHERE codigo=? AND condicion=1 AND idvariante<>?)",
					array($codigo, $codigo, $codigo, $variante ? (int)$variante['idvariante'] : 0), 0);
				if ($usado > 0) $errores[] = 'El código ' . $codigo . ' ya lo usa otro artículo o talla/color';
			}
			if (!$art && $categoria === '') $errores[] = 'Categoría vacía (indícala en al menos una fila del artículo nuevo)';
			$accion = $errores ? 'error' : ($variante ? 'actualizar' : 'crear');
			$res[$accion]++;
			$res['filas'][] = array(
				'fila' => (int)$f['_fila'], 'accion' => $accion, 'errores' => $errores, 'variante' => true,
				'nombre' => $nombre, 'talla' => $talla, 'color' => $color, 'codigo' => $codigo,
				'categoria' => $categoria, 'categoria_nueva' => $catNueva, 'idcategoria' => $idcat,
				'unidad' => $unidad === '' ? 'und' : $unidad, 'idunidad' => $idunidad,
				'stock' => cantidadSegura($stock, false), 'stock_minimo' => cantidadSegura($stockMin, false),
				'precio_compra' => round($pc, 2), 'precio_venta' => round($pv, 2),
				'descripcion' => isset($f['descripcion']) ? mb_substr(trim($f['descripcion']), 0, 256) : '',
				'idexistente' => $art ? (int)$art['idarticulo'] : 0,
				'idvariante_existente' => $variante ? (int)$variante['idvariante'] : 0,
				'stock_actual' => $variante ? round((float)$variante['stock'], 3) : null
			);
		}
		$res['categorias_nuevas'] = array_values($res['categorias_nuevas']);
		return $res;
	}

	private function validarArticulosSimples(array $filas)
	{
		$cats = array(); foreach (dbAll("SELECT idcategoria, nombre FROM categoria") as $c) { $cats[self::normalizarValor($c['nombre'])] = (int)$c['idcategoria']; }
		$unis = array(); $fraccionUnidad = array();
		foreach (dbAll("SELECT idunidad, nombre, abreviatura, permite_fraccion FROM unidad_medida WHERE condicion=1") as $u) { $unis[self::normalizarValor($u['abreviatura'])] = (int)$u['idunidad']; $unis[self::normalizarValor($u['nombre'])] = (int)$u['idunidad']; $fraccionUnidad[(int)$u['idunidad']] = (int)$u['permite_fraccion'] === 1; }
		$usaFracciones = function_exists('negocioTiene') && negocioTiene('fracciones');
		$porCodigo = array(); $porNombre = array();
		foreach (dbAll("SELECT idarticulo, codigo, nombre, stock FROM articulo") as $a) {
			if ($a['codigo'] !== null && trim($a['codigo']) !== '') $porCodigo[mb_strtolower(trim($a['codigo']))] = $a;
			$porNombre[mb_strtolower(html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8'))] = $a;
		}
		$vistos = array();
		$res = array('crear' => 0, 'actualizar' => 0, 'error' => 0, 'categorias_nuevas' => array(), 'filas' => array());
		foreach ($filas as $f) {
			$errores = array();
			$nombre = isset($f['nombre']) ? trim($f['nombre']) : '';
			$codigo = isset($f['codigo']) ? trim($f['codigo']) : '';
			$categoria = isset($f['categoria']) ? trim($f['categoria']) : '';
			$unidad = isset($f['unidad']) ? trim($f['unidad']) : '';
			if ($nombre === '') $errores[] = 'Nombre vacío';
			if (mb_strlen($nombre) > 100) $errores[] = 'Nombre supera 100 caracteres';
			if (mb_strlen($codigo) > 50) $errores[] = 'Código supera 50 caracteres';
			$clave = $codigo !== '' ? 'c:' . mb_strtolower($codigo) : 'n:' . mb_strtolower($nombre);
			if (isset($vistos[$clave])) $errores[] = 'Duplicado en el archivo (fila ' . $vistos[$clave] . ')';
			$vistos[$clave] = $f['_fila'];
			$idcat = 0; $catNueva = false;
			if ($categoria !== '') {
				$k = self::normalizarValor($categoria);
				if (isset($cats[$k])) $idcat = $cats[$k]; else { $catNueva = true; $res['categorias_nuevas'][$k] = $categoria; }
			}
			$idunidad = 0;
			if ($unidad !== '') { $k = self::normalizarValor($unidad); if (isset($unis[$k])) $idunidad = $unis[$k]; else $errores[] = 'Unidad "' . $unidad . '" no existe (crea la unidad primero o deja vacío para "und")'; }
			if ($idunidad === 0 && $unidad === '') { $idunidad = isset($unis['und']) ? $unis['und'] : (count($unis) ? reset($unis) : 0); }
			if ($idunidad === 0) $errores[] = 'No hay unidades de medida registradas';
			$stock = $this->numero(isset($f['stock']) ? $f['stock'] : '', 0);
			$stockMin = $this->numero(isset($f['stock_minimo']) ? $f['stock_minimo'] : '', 1);
			$pc = $this->numero(isset($f['precio_compra']) ? $f['precio_compra'] : '', 0);
			$pv = $this->numero(isset($f['precio_venta']) ? $f['precio_venta'] : '', 0);
			if ($stock < 0 || $stockMin < 0 || $pc < 0 || $pv < 0) $errores[] = 'Valores negativos';
			$existente = null;
			if ($codigo !== '' && isset($porCodigo[mb_strtolower($codigo)])) $existente = $porCodigo[mb_strtolower($codigo)];
			elseif (isset($porNombre[mb_strtolower($nombre)])) $existente = $porNombre[mb_strtolower($nombre)];
			// Un nombre existente con otro codigo distinto choca con la restriccion UNIQUE(nombre)
			if ($existente === null && isset($porNombre[mb_strtolower($nombre)])) $existente = $porNombre[mb_strtolower($nombre)];
			$accion = $errores ? 'error' : ($existente ? 'actualizar' : 'crear');
			if ($accion === 'crear' && $categoria === '') { $errores[] = 'Categoría vacía (obligatoria para artículos nuevos)'; $accion = 'error'; }
			$res[$accion]++;
			$res['filas'][] = array(
				'fila' => (int)$f['_fila'], 'accion' => $accion, 'errores' => $errores,
				'nombre' => $nombre, 'codigo' => $codigo, 'categoria' => $categoria, 'categoria_nueva' => $catNueva, 'idcategoria' => $idcat,
				'unidad' => $unidad === '' ? 'und' : $unidad, 'idunidad' => $idunidad,
				'stock' => cantidadSegura($stock, $usaFracciones && !empty($fraccionUnidad[$idunidad])), 'stock_minimo' => cantidadSegura($stockMin, $usaFracciones && !empty($fraccionUnidad[$idunidad])), 'precio_compra' => round($pc, 2), 'precio_venta' => round($pv, 2),
				'descripcion' => isset($f['descripcion']) ? mb_substr(trim($f['descripcion']), 0, 256) : '',
				'idexistente' => $existente ? (int)$existente['idarticulo'] : 0, 'stock_actual' => $existente ? round((float)$existente['stock'], 3) : null
			);
		}
		$res['categorias_nuevas'] = array_values($res['categorias_nuevas']);
		return $res;
	}

	public function validarPersonas(array $filas, $tipoPersona)
	{
		$existentes = array();
		foreach (dbAll("SELECT idpersona, num_documento, nombre FROM persona WHERE tipo_persona=?", array($tipoPersona)) as $p) {
			if ($p['num_documento'] !== null && trim($p['num_documento']) !== '') $existentes['d:' . trim($p['num_documento'])] = (int)$p['idpersona'];
			$existentes['n:' . mb_strtolower(html_entity_decode($p['nombre'], ENT_QUOTES, 'UTF-8'))] = (int)$p['idpersona'];
		}
		$tiposDoc = array('DNI', 'RUC', 'CEDULA', 'PASAPORTE', 'OTRO');
		$vistos = array();
		$res = array('crear' => 0, 'actualizar' => 0, 'error' => 0, 'filas' => array());
		foreach ($filas as $f) {
			$errores = array();
			$nombre = isset($f['nombre']) ? trim($f['nombre']) : '';
			$doc = isset($f['num_documento']) ? preg_replace('/\s+/', '', $f['num_documento']) : '';
			$tipoDoc = isset($f['tipo_documento']) ? strtoupper(self::normalizarValor($f['tipo_documento'])) : '';
			if ($tipoDoc === '') $tipoDoc = (strlen($doc) === 11 ? 'RUC' : 'DNI');
			if (!in_array($tipoDoc, $tiposDoc, true)) $errores[] = 'Tipo de documento "' . $tipoDoc . '" no válido (DNI, RUC, CEDULA, PASAPORTE, OTRO)';
			$email = isset($f['email']) ? trim($f['email']) : '';
			if ($nombre === '') $errores[] = 'Nombre vacío';
			if (mb_strlen($nombre) > 100) $errores[] = 'Nombre supera 100 caracteres';
			if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'Email inválido';
			$clave = $doc !== '' ? 'd:' . $doc : 'n:' . mb_strtolower($nombre);
			if (isset($vistos[$clave])) $errores[] = 'Duplicado en el archivo (fila ' . $vistos[$clave] . ')';
			$vistos[$clave] = $f['_fila'];
			$id = isset($existentes[$clave]) ? $existentes[$clave] : (isset($existentes['n:' . mb_strtolower($nombre)]) ? $existentes['n:' . mb_strtolower($nombre)] : 0);
			$accion = $errores ? 'error' : ($id ? 'actualizar' : 'crear');
			$res[$accion]++;
			$res['filas'][] = array(
				'fila' => (int)$f['_fila'], 'accion' => $accion, 'errores' => $errores, 'idexistente' => $id,
				'nombre' => $nombre, 'tipo_documento' => $tipoDoc, 'num_documento' => mb_substr($doc, 0, 20),
				'direccion' => isset($f['direccion']) ? mb_substr(trim($f['direccion']), 0, 70) : '', 'telefono' => isset($f['telefono']) ? mb_substr(trim($f['telefono']), 0, 20) : '', 'email' => mb_substr($email, 0, 50)
			);
		}
		return $res;
	}

	// ------------------------------------------------------------------
	// Importacion
	// ------------------------------------------------------------------

	public function importarArticulos(array $validacion, array $op, $idusuario)
	{
		$crearCat = !empty($op['crear_categorias']);
		$actualizar = !empty($op['actualizar_existentes']);
		$ajustarStock = !empty($op['actualizar_stock']);
		$res = array('creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'categorias' => 0, 'ajustes' => 0);
		$ok = dbTransaccion(function () use ($validacion, $crearCat, $actualizar, $ajustarStock, $idusuario, &$res) {
			$cats = array(); foreach (dbAll("SELECT idcategoria, nombre FROM categoria") as $c) { $cats[Importacion::normalizarValor($c['nombre'])] = (int)$c['idcategoria']; }
			$articulosVariante = array(); // nombre normalizado => idarticulo creado o existente
			foreach ($validacion['filas'] as $r) {
				if ($r['accion'] === 'error') { $res['omitidos']++; continue; }
				$idcat = (int)$r['idcategoria'];
				if ($idcat === 0 && $r['categoria'] !== '') {
					$k = Importacion::normalizarValor($r['categoria']);
					if (isset($cats[$k])) $idcat = $cats[$k];
					elseif ($crearCat) {
						$idcat = dbInsert("INSERT INTO categoria(nombre, descripcion, condicion) VALUES(?, 'Creada por importación', 1)", array(mb_substr($r['categoria'], 0, 50)));
						if ($idcat <= 0) return false;
						$cats[$k] = $idcat; $res['categorias']++;
					}
				}
				if (!empty($r['variante'])) {
					if (Importacion::importarFilaVariante($r, $idcat, $actualizar, $ajustarStock, $idusuario, $res, $articulosVariante) === false) return false;
					continue;
				}
				$nombre = limpiarCadena($r['nombre']); $desc = limpiarCadena($r['descripcion']); $codigo = limpiarCadena($r['codigo']);
				if ($r['accion'] === 'crear') {
					if ($idcat === 0) { $res['omitidos']++; continue; }
					$id = dbInsert("INSERT INTO articulo(idcategoria, idunidad, codigo, nombre, stock, stock_minimo, precio_compra, precio_venta, descripcion, imagen, condicion) VALUES(?,?,?,?,?,?,?,?,?,'',1)",
						array($idcat, (int)$r['idunidad'], $codigo, $nombre, (float)$r['stock'], (float)$r['stock_minimo'], (float)$r['precio_compra'], (float)$r['precio_venta'], $desc));
					if ($id <= 0) return false;
					if ((float)$r['stock'] > 0) {
						dbInsert("INSERT INTO ajuste_inventario(idarticulo, idalmacen, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion) VALUES(?,(SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1),?,'ENTRADA','INICIAL',?,0,?,?,'Stock inicial por importación')",
							array($id, (int)$idusuario, (float)$r['stock'], (float)$r['stock'], (float)$r['precio_compra']));
					}
					$res['creados']++;
				} else {
					if (!$actualizar) { $res['omitidos']++; continue; }
					$idart = (int)$r['idexistente'];
					$sets = "nombre=?, precio_compra=?, precio_venta=?, stock_minimo=?";
					$params = array($nombre, (float)$r['precio_compra'], (float)$r['precio_venta'], (float)$r['stock_minimo']);
					if ($idcat > 0) { $sets .= ", idcategoria=?"; $params[] = $idcat; }
					if ((int)$r['idunidad'] > 0 && $r['unidad'] !== 'und') { $sets .= ", idunidad=?"; $params[] = (int)$r['idunidad']; }
					if ($codigo !== '') { $sets .= ", codigo=?"; $params[] = $codigo; }
					if ($desc !== '') { $sets .= ", descripcion=?"; $params[] = $desc; }
					$params[] = $idart;
					if (!dbExec("UPDATE articulo SET $sets WHERE idarticulo=?", $params)) return false;
					if ($ajustarStock) {
						$act = dbRow("SELECT stock FROM articulo WHERE idarticulo=? FOR UPDATE", array($idart));
						$anterior = $act ? (float)$act['stock'] : 0; $nuevo = (float)$r['stock'];
						if (abs($nuevo - $anterior) > 0.0001) {
							dbInsert("INSERT INTO ajuste_inventario(idarticulo, idalmacen, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion) VALUES(?,(SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1),?,?,'CONTEO',?,?,?,?,'Conteo por importación')",
								array($idart, (int)$idusuario, $nuevo > $anterior ? 'ENTRADA' : 'SALIDA', abs($nuevo - $anterior), $anterior, $nuevo, (float)$r['precio_compra']));
							dbExec("UPDATE articulo SET stock=? WHERE idarticulo=?", array($nuevo, $idart));
							// Si el conteo baja el stock, los lotes se recortan (primero lo que vence antes)
							if (!Lote::ajustarAlStock($idart)) return false;
							$res['ajustes']++;
						}
					}
					$res['actualizados']++;
				}
			}
			// Articulos con tallas/colores: su stock es la suma de las combinaciones
			foreach (array_unique(array_values($articulosVariante)) as $idArt) {
				if (!Variante::recalcularArticulo($idArt)) return false;
			}
			// Stock por almacen: lo que la importacion cambio en el total va al almacen principal
			if (!Stock::cuadrarDescuadrados()) return false;
			return true;
		});
		if ($ok === false) return array('ok' => false, 'message' => 'La importación falló y se revirtió por completo. Revisa logs/app.log.');
		$res['ok'] = true;
		return $res;
	}

	/**
	 * Importa una fila talla/color: crea el articulo la primera vez que aparece su
	 * nombre y luego crea o actualiza la combinacion. El stock se registra como
	 * ajuste (INICIAL o CONTEO) con su idvariante para que quede en el kardex.
	 */
	public static function importarFilaVariante(array $r, $idcat, $actualizar, $ajustarStock, $idusuario, array &$res, array &$articulosVariante)
	{
		$clave = mb_strtolower($r['nombre']);
		$nombre = limpiarCadena($r['nombre']);
		$talla = limpiarCadena($r['talla']);
		$color = limpiarCadena($r['color']);
		$codigo = limpiarCadena($r['codigo']);
		$idart = isset($articulosVariante[$clave]) ? $articulosVariante[$clave] : (int)$r['idexistente'];
		if ($idart <= 0) {
			if ((int)$idcat <= 0) { $res['omitidos']++; return true; }
			$idart = dbInsert("INSERT INTO articulo(idcategoria, idunidad, codigo, nombre, stock, stock_minimo, precio_compra, precio_venta, descripcion, imagen, condicion) VALUES(?,?,'',?,0,0,?,?,?,'',1)",
				array((int)$idcat, (int)$r['idunidad'], $nombre, (float)$r['precio_compra'], (float)$r['precio_venta'], limpiarCadena($r['descripcion'])));
			if ($idart <= 0) return false;
			$res['creados']++;
		}
		$articulosVariante[$clave] = $idart;
		$precioArticulo = (float)dbValue("SELECT precio_venta FROM articulo WHERE idarticulo=?", array($idart), 0);
		// Precio propio solo si difiere del articulo (0 = usa el del articulo)
		$precioVariante = abs((float)$r['precio_venta'] - $precioArticulo) > 0.004 ? (float)$r['precio_venta'] : 0;
		$existente = dbRow("SELECT idvariante, stock, condicion FROM articulo_variante WHERE idarticulo=? AND talla=? AND color=? FOR UPDATE", array($idart, $talla, $color));
		$stockArt = (float)dbValue("SELECT stock FROM articulo WHERE idarticulo=? FOR UPDATE", array($idart), 0);

		if (!$existente) {
			$idvar = dbInsert("INSERT INTO articulo_variante (idarticulo,talla,color,codigo,stock,stock_minimo,precio_venta,orden,condicion) VALUES (?,?,?,?,?,?,?,?,1)",
				array($idart, $talla, $color, $codigo !== '' ? $codigo : null, (float)$r['stock'], (float)$r['stock_minimo'], $precioVariante, (int)$r['fila']));
			if ($idvar <= 0) return false;
			if ((float)$r['stock'] > 0) {
				dbInsert("INSERT INTO ajuste_inventario(idarticulo, idvariante, idalmacen, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion) VALUES(?,?,(SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1),?,'ENTRADA','INICIAL',?,?,?,?,'Stock inicial por importación')",
					array($idart, $idvar, (int)$idusuario, (float)$r['stock'], $stockArt, $stockArt + (float)$r['stock'], (float)$r['precio_compra']));
				dbExec("UPDATE articulo SET stock=stock+? WHERE idarticulo=?", array((float)$r['stock'], $idart));
				$res['ajustes']++;
			}
			if ((int)$r['idexistente'] > 0) { $res['actualizados']++; }
			return true;
		}
		if (!$actualizar) { $res['omitidos']++; return true; }
		$idvar = (int)$existente['idvariante'];
		$sets = "stock_minimo=?, precio_venta=?, condicion=1";
		$params = array((float)$r['stock_minimo'], $precioVariante);
		if ($codigo !== '') { $sets .= ", codigo=?"; $params[] = $codigo; }
		$params[] = $idvar;
		if (!dbExec("UPDATE articulo_variante SET $sets WHERE idvariante=?", $params)) return false;
		if ($ajustarStock) {
			$anterior = (float)$existente['stock'];
			$nuevo = (float)$r['stock'];
			if (abs($nuevo - $anterior) > 0.0001) {
				$delta = $nuevo - $anterior;
				dbInsert("INSERT INTO ajuste_inventario(idarticulo, idvariante, idalmacen, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion) VALUES(?,?,(SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1),?,?,'CONTEO',?,?,?,?,'Conteo por importación')",
					array($idart, $idvar, (int)$idusuario, $delta > 0 ? 'ENTRADA' : 'SALIDA', abs($delta), $stockArt, $stockArt + $delta, (float)$r['precio_compra']));
				if (!Variante::moverStock($idvar, $delta)) return false;
				dbExec("UPDATE articulo SET stock=stock+? WHERE idarticulo=?", array($delta, $idart));
				$res['ajustes']++;
			}
		}
		$res['actualizados']++;
		return true;
	}

	public function importarPersonas(array $validacion, $tipoPersona, array $op)
	{
		$actualizar = !empty($op['actualizar_existentes']);
		$res = array('creados' => 0, 'actualizados' => 0, 'omitidos' => 0);
		$ok = dbTransaccion(function () use ($validacion, $tipoPersona, $actualizar, &$res) {
			foreach ($validacion['filas'] as $r) {
				if ($r['accion'] === 'error') { $res['omitidos']++; continue; }
				$p = array(limpiarCadena($r['nombre']), $r['tipo_documento'], limpiarCadena($r['num_documento']), limpiarCadena($r['direccion']), limpiarCadena($r['telefono']), limpiarCadena($r['email']));
				if ($r['accion'] === 'crear') {
					$id = dbInsert("INSERT INTO persona(tipo_persona, nombre, tipo_documento, num_documento, direccion, telefono, email, condicion) VALUES(?,?,?,?,?,?,?,1)", array_merge(array($tipoPersona), $p));
					if ($id <= 0) return false;
					$res['creados']++;
				} else {
					if (!$actualizar) { $res['omitidos']++; continue; }
					$p[] = (int)$r['idexistente'];
					if (!dbExec("UPDATE persona SET nombre=?, tipo_documento=?, num_documento=?, direccion=?, telefono=?, email=?, condicion=1 WHERE idpersona=?", $p)) return false;
					$res['actualizados']++;
				}
			}
			return true;
		});
		if ($ok === false) return array('ok' => false, 'message' => 'La importación falló y se revirtió por completo.');
		$res['ok'] = true;
		return $res;
	}

	// ------------------------------------------------------------------
	// Plantillas
	// ------------------------------------------------------------------

	public function plantillaCsv($tipo)
	{
		$cols = self::columnas($tipo);
		$cab = array(); $ej = array();
		foreach ($cols as $c) { $cab[] = $c[0]; $ej[] = $c[2]; }
		$h = fopen('php://temp', 'r+');
		fwrite($h, "\xEF\xBB\xBF");
		fputcsv($h, $cab, ';', '"', '\\');
		fputcsv($h, $ej, ';', '"', '\\');
		rewind($h);
		$out = stream_get_contents($h);
		fclose($h);
		return $out;
	}

	/** XLSX minimo (una hoja, cadenas en linea) generado con ZipArchive. */
	public function plantillaXlsx($tipo)
	{
		$cols = self::columnas($tipo);
		$filas = array(array(), array());
		foreach ($cols as $c) { $filas[0][] = $c[0]; $filas[1][] = $c[2]; }
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="' . count($cols) . '" width="22" customWidth="1"/></cols><sheetData>';
		foreach ($filas as $ri => $fila) {
			$sheet .= '<row r="' . ($ri + 1) . '">';
			foreach ($fila as $ci => $v) {
				$sheet .= '<c r="' . self::indiceColumna($ci) . ($ri + 1) . '" t="inlineStr"><is><t>' . htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';
		$tmp = tempnam(sys_get_temp_dir(), 'plantilla');
		$zip = new ZipArchive();
		$zip->open($tmp, ZipArchive::OVERWRITE);
		$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
		$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
		$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . ucfirst($tipo) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
		$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
		$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
		$zip->close();
		$data = file_get_contents($tmp);
		@unlink($tmp);
		return $data;
	}

	private static function indiceColumna($i)
	{
		$s = '';
		$i++;
		while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = (int)(($i - $m - 1) / 26); }
		return $s;
	}
}
