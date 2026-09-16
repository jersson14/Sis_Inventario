<?php
/**
 * Perfil de negocio (rubro de la tienda).
 *
 * El sistema sirve a rubros distintos y cada uno necesita cosas distintas:
 * una bodega controla vencimientos, una ferreteria vende fracciones y una
 * tienda de ropa maneja tallas y colores. En lugar de mantener versiones
 * separadas, hay un unico sistema con un perfil que enciende o apaga cada
 * funcion y cambia como se nombran las cosas.
 *
 * Todo el codigo debe preguntar por la CAPACIDAD, no por el rubro:
 *   if (negocioTiene('vencimientos')) { ... }        // bien
 *   if (perfilNegocio() === 'ABARROTES') { ... }     // evitar
 *
 * Asi, mañana se puede crear un rubro nuevo (farmacia, licoreria) combinando
 * capacidades existentes sin tocar los modulos.
 *
 * Requiere config/Conexion.php cargado (lo hace config/seguridad.php).
 */

if (!function_exists('perfilesNegocio')) {

	/**
	 * Catalogo de rubros. Cada uno declara que capacidades enciende y con que
	 * palabras habla la interfaz.
	 */
	function perfilesNegocio() {
		return array(
			'GENERAL' => array(
				'nombre'      => 'General',
				'descripcion' => 'Inventario estándar, sin funciones específicas de rubro.',
				'icono'       => 'fa-shopping-bag',
				'capacidades' => array(),
				'textos'      => array(),
			),
			'ABARROTES' => array(
				'nombre'      => 'Abarrotes / bodega / minimarket',
				'descripcion' => 'Controla fechas de vencimiento y lotes. Avisa de lo que está por vencer y despacha primero lo más antiguo.',
				'icono'       => 'fa-shopping-basket',
				'capacidades' => array('vencimientos', 'lotes', 'fracciones'),
				'textos'      => array(
					'articulo'  => 'Producto',
					'articulos' => 'Productos',
				),
			),
			'FERRETERIA' => array(
				'nombre'      => 'Ferretería / materiales',
				'descripcion' => 'Permite vender por fracción (metros, kilos), equivalencias de empaque y precio por mayor.',
				'icono'       => 'fa-wrench',
				'capacidades' => array('fracciones', 'equivalencias', 'precio_mayor'),
				'textos'      => array(
					'articulo'  => 'Artículo',
					'articulos' => 'Artículos',
				),
			),
			'ROPA' => array(
				'nombre'      => 'Ropa / calzado / boutique',
				'descripcion' => 'Maneja tallas y colores con stock propio por cada combinación.',
				'icono'       => 'fa-shopping-bag',
				'capacidades' => array('variantes', 'temporada'),
				'textos'      => array(
					'articulo'  => 'Prenda',
					'articulos' => 'Prendas',
				),
			),
		);
	}

	/**
	 * Todas las capacidades que existen, con su explicacion. Sirve para la
	 * pantalla de configuracion y como referencia unica de nombres validos.
	 */
	function capacidadesNegocio() {
		return array(
			'vencimientos'  => 'Fecha de vencimiento por lote, alertas de próximos a vencer y vencidos',
			'lotes'         => 'Control de lotes: la salida toma primero el lote que vence antes',
			'fracciones'    => 'Cantidades con decimales (1.5 m, 0.75 kg)',
			'equivalencias' => 'Equivalencias de empaque (una caja contiene N unidades)',
			'precio_mayor'  => 'Precio por mayor a partir de cierta cantidad',
			'variantes'     => 'Tallas y colores con stock propio por combinación',
			'temporada'     => 'Temporada y colección en la ficha del artículo',
		);
	}

	/**
	 * Rubro configurado. Se consulta una sola vez por peticion.
	 */
	function perfilNegocio() {
		static $perfil = null;
		if ($perfil !== null) {
			return $perfil;
		}
		$perfil = 'GENERAL';
		// La columna puede no existir todavia si no se aplico la migracion.
		$valor = dbValue("SELECT tipo_negocio FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), '');
		$valor = strtoupper(trim((string)$valor));
		if ($valor !== '' && isset(perfilesNegocio()[$valor])) {
			$perfil = $valor;
		}
		return $perfil;
	}

	/**
	 * Definicion completa del rubro activo.
	 */
	function definicionNegocio($clave = null) {
		$perfiles = perfilesNegocio();
		$clave = $clave === null ? perfilNegocio() : strtoupper((string)$clave);
		return isset($perfiles[$clave]) ? $perfiles[$clave] : $perfiles['GENERAL'];
	}

	/**
	 * ¿El rubro activo tiene esta capacidad encendida?
	 */
	function negocioTiene($capacidad) {
		$def = definicionNegocio();
		return in_array((string)$capacidad, $def['capacidades'], true);
	}

	/**
	 * Palabra que usa este rubro para un concepto ('articulo', 'articulos').
	 * Devuelve el valor por defecto si el rubro no la personaliza.
	 */
	function textoNegocio($clave, $porDefecto = '') {
		$def = definicionNegocio();
		if (isset($def['textos'][$clave]) && $def['textos'][$clave] !== '') {
			return $def['textos'][$clave];
		}
		return $porDefecto !== '' ? $porDefecto : $clave;
	}

	/**
	 * Resumen para el frontend (se inyecta en header.php como window.appNegocio).
	 */
	function negocioParaJs() {
		$def = definicionNegocio();
		return array(
			'perfil'      => perfilNegocio(),
			'nombre'      => $def['nombre'],
			'capacidades' => array_values($def['capacidades']),
			'textos'      => (object)$def['textos'],
		);
	}

	// ------------------------------------------------------------------
	// Cantidades (capacidad 'fracciones')
	// ------------------------------------------------------------------

	/**
	 * Normaliza una cantidad recibida. Con fraccion se aceptan hasta 3
	 * decimales (lo que guarda la BD); sin fraccion se redondea al entero.
	 * Nunca devuelve negativos.
	 */
	function cantidadSegura($valor, $permiteFraccion) {
		$num = is_numeric($valor) ? (float)$valor : (float)str_replace(',', '.', (string)$valor);
		if (!is_finite($num) || $num < 0) {
			return 0.0;
		}
		return $permiteFraccion ? round($num, 3) : (float)round($num);
	}

	/**
	 * Cantidad legible: sin decimales si es entera, con los necesarios si no
	 * (12, 1.5, 0.75).
	 */
	function formatearCantidad($valor) {
		$num = round((float)$valor, 3);
		if (abs($num - round($num)) < 0.0005) {
			return number_format($num, 0, '.', ',');
		}
		return rtrim(rtrim(number_format($num, 3, '.', ','), '0'), '.');
	}

	/**
	 * Para cada articulo, ¿admite cantidades con decimales? Depende de que el
	 * rubro tenga 'fracciones' y de que su unidad de medida lo permita.
	 * Devuelve array idarticulo => bool.
	 */
	function articulosPermitenFraccion(array $ids) {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$mapa = array();
		foreach ($ids as $id) {
			$mapa[$id] = false;
		}
		if (!$ids || !negocioTiene('fracciones')) {
			return $mapa;
		}
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$filas = dbAll(
			"SELECT a.idarticulo, IFNULL(u.permite_fraccion,0) AS permite_fraccion
			 FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			 WHERE a.idarticulo IN ($ph)",
			$ids
		);
		foreach ($filas as $f) {
			$mapa[(int)$f['idarticulo']] = (int)$f['permite_fraccion'] === 1;
		}
		return $mapa;
	}

	// ------------------------------------------------------------------
	// Presentaciones y precio por mayor (capacidades 'equivalencias' y 'precio_mayor')
	// ------------------------------------------------------------------

	/**
	 * Presentaciones activas de un articulo, validadas para usarse en un
	 * detalle. Devuelve array idpresentacion => fila, o vacio si el rubro no
	 * tiene equivalencias.
	 */
	function presentacionesArticulo($idarticulo) {
		if (!negocioTiene('equivalencias')) {
			return array();
		}
		$out = array();
		$filas = dbAll(
			"SELECT idpresentacion, idarticulo, nombre, factor, precio_venta, precio_compra, codigo
			 FROM articulo_presentacion WHERE idarticulo=? AND condicion=1 ORDER BY factor ASC",
			array((int)$idarticulo)
		);
		foreach ($filas as $f) {
			$out[(int)$f['idpresentacion']] = $f;
		}
		return $out;
	}

	/**
	 * Escalas de precio por mayor de un articulo (cantidad_minima ascendente).
	 */
	function escalasPrecioArticulo($idarticulo) {
		if (!negocioTiene('precio_mayor')) {
			return array();
		}
		return dbAll(
			"SELECT cantidad_minima, precio FROM articulo_precio_escala WHERE idarticulo=? ORDER BY cantidad_minima ASC",
			array((int)$idarticulo)
		);
	}

	/**
	 * Resuelve la presentacion de una linea de detalle. Devuelve
	 * array(idpresentacion|null, factor, nombre) o false si la presentacion no
	 * existe, esta inactiva o no pertenece al articulo.
	 */
	function resolverPresentacionDetalle($idarticulo, $idpresentacion) {
		$idpresentacion = (int)$idpresentacion;
		if ($idpresentacion <= 0) {
			return array(null, 1.0, '');
		}
		if (!negocioTiene('equivalencias')) {
			return false;
		}
		$p = dbRow(
			"SELECT idpresentacion, nombre, factor FROM articulo_presentacion
			 WHERE idpresentacion=? AND idarticulo=? AND condicion=1 LIMIT 1",
			array($idpresentacion, (int)$idarticulo)
		);
		if (!$p || (float)$p['factor'] <= 0) {
			return false;
		}
		return array((int)$p['idpresentacion'], round((float)$p['factor'], 3), $p['nombre']);
	}

	/**
	 * Valida y normaliza un rubro recibido por formulario.
	 */
	function normalizarPerfilNegocio($valor) {
		$valor = strtoupper(trim((string)$valor));
		return isset(perfilesNegocio()[$valor]) ? $valor : 'GENERAL';
	}
}
