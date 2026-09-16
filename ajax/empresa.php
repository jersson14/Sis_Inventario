<?php
require_once "../config/seguridad.php";

$op = isset($_GET['op']) ? (string)$_GET['op'] : '';

// publicBrand es publico (lo usa la pantalla de login): solo sesion + cabeceras.
if ($op === 'publicBrand') {
    iniciarSesionSegura();
    enviarCabecerasSeguridad();
} else {
    requiereLogin();
}
require_once "../modelos/Empresa.php";

$empresa = new Empresa();

/** Monedas aceptadas (coinciden con los helpers de Conexion.php). */
$MONEDAS = array('PEN', 'USD', 'EUR', 'MXN', 'COP', 'CLP', 'ARS', 'BOB', 'UYU', 'PYG', 'BRL', 'GTQ', 'CRC', 'DOP', 'HNL', 'NIO');

/** Color hex #RRGGBB o el default. */
function colorHexSeguro($valor, $default)
{
    $valor = strtolower(trim((string)$valor));
    return preg_match('/^#[0-9a-f]{6}$/', $valor) ? $valor : $default;
}

/** Serie alfanumerica (max 10) en mayusculas o el default. */
function serieSegura($valor, $default)
{
    $valor = strtoupper(trim((string)$valor));
    return preg_match('/^[A-Z0-9]{1,10}$/', $valor) ? $valor : $default;
}

/** Resuelve la URL publica del logo configurado (o el generico). */
function urlLogoEmpresa($logo)
{
    $logo = nombreArchivoSeguro($logo);
    if ($logo !== '') {
        if (is_file(__DIR__ . '/../files/empresa/' . $logo)) {
            return '../files/empresa/' . $logo;
        }
        if (is_file(__DIR__ . '/../vistas/' . $logo)) {
            return $logo;
        }
    }
    return '../public/img/brand-store.svg';
}

switch ($op) {
    case 'publicBrand':
        $cfg = $empresa->obtener();
        if (!$cfg) {
            responderJson(array(
                'nombre_comercial' => 'Mi Tienda',
                'razon_social' => '',
                'color_primario' => '#0f766e',
                'color_secundario' => '#f59e0b',
                'logo_url' => '../public/img/brand-store.svg',
                'moneda' => 'PEN',
                'simbolo_moneda' => obtenerSimboloMoneda('PEN')
            ));
        }
        $moneda = !empty($cfg['moneda']) ? strtoupper($cfg['moneda']) : 'PEN';
        responderJson(array(
            'nombre_comercial' => !empty($cfg['nombre_comercial']) ? $cfg['nombre_comercial'] : 'Mi Tienda',
            'razon_social' => isset($cfg['razon_social']) ? (string)$cfg['razon_social'] : '',
            'color_primario' => colorHexSeguro($cfg['color_primario'], '#0f766e'),
            'color_secundario' => colorHexSeguro($cfg['color_secundario'], '#f59e0b'),
            'logo_url' => urlLogoEmpresa($cfg['logo']),
            'moneda' => $moneda,
            'simbolo_moneda' => obtenerSimboloMoneda($moneda)
        ));
        break;

    case 'defaults':
        $cfg = $empresa->obtener();
        if (!$cfg) {
            responderJson(array(
                'serie_boleta' => 'B001',
                'serie_factura' => 'F001',
                'serie_ticket' => 'T001',
                'serie_cotizacion' => 'COT',
                'impuesto_default' => '18.00',
                'moneda' => 'PEN',
                'simbolo_moneda' => obtenerSimboloMoneda('PEN'),
                'mensaje_ticket' => 'Gracias por su compra'
            ));
        }
        $moneda = !empty($cfg['moneda']) ? strtoupper($cfg['moneda']) : 'PEN';
        responderJson(array(
            'serie_boleta' => $cfg['serie_boleta'],
            'serie_factura' => $cfg['serie_factura'],
            'serie_ticket' => $cfg['serie_ticket'],
            'serie_cotizacion' => isset($cfg['serie_cotizacion']) ? $cfg['serie_cotizacion'] : 'COT',
            'impuesto_default' => $cfg['impuesto_default'],
            'moneda' => $moneda,
            'simbolo_moneda' => obtenerSimboloMoneda($moneda),
            'mensaje_ticket' => isset($cfg['mensaje_ticket']) ? $cfg['mensaje_ticket'] : 'Gracias por su compra'
        ));
        break;

    case 'mostrar':
        $cfg = $empresa->obtener();
        if ($cfg) {
            $cfg['logo_url'] = urlLogoEmpresa($cfg['logo']);
        }
        responderJson($cfg ? $cfg : array());
        break;

    case 'guardaryeditar':
        requierePermiso(array('empresa', 'acceso'));

        // Logo: se conserva el actual salvo que se suba uno nuevo valido
        $actual = $empresa->obtener();
        $logo = nombreArchivoSeguro(isset($_POST['logoactual']) ? $_POST['logoactual'] : '');
        if ($logo === '' && $actual && !empty($actual['logo'])) {
            $logo = nombreArchivoSeguro($actual['logo']);
        }
        if (isset($_FILES['logo']) && isset($_FILES['logo']['error']) && (int)$_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            list($okImg, $resImg) = guardarImagenSubida('logo', '../files/empresa');
            if (!$okImg) {
                echo $resImg;
                break;
            }
            // Borrar el logo anterior si estaba en files/empresa
            if ($logo !== '' && $logo !== $resImg && is_file('../files/empresa/' . $logo)) {
                @unlink('../files/empresa/' . $logo);
            }
            $logo = $resImg;
        }

        $nombre_comercial = substr(limpiarCadena(isset($_POST['nombre_comercial']) ? $_POST['nombre_comercial'] : ''), 0, 120);
        if ($nombre_comercial === '') {
            echo 'El nombre comercial es obligatorio.';
            break;
        }

        $correo = trim((string)(isset($_POST['correo']) ? $_POST['correo'] : ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo 'El correo electronico no es valido.';
            break;
        }

        $impuesto = decimalSeguro(isset($_POST['impuesto_default']) ? $_POST['impuesto_default'] : 18, 2, 18.0);
        if ($impuesto < 0 || $impuesto > 100) {
            echo 'El impuesto debe estar entre 0 y 100.';
            break;
        }

        $moneda = strtoupper(trim((string)(isset($_POST['moneda']) ? $_POST['moneda'] : 'PEN')));
        if (!in_array($moneda, $MONEDAS, true)) {
            $moneda = 'PEN';
        }

        $data = array(
            'nombre_comercial' => $nombre_comercial,
            'razon_social' => substr(limpiarCadena(isset($_POST['razon_social']) ? $_POST['razon_social'] : ''), 0, 150),
            'ruc' => substr(limpiarCadena(isset($_POST['ruc']) ? $_POST['ruc'] : ''), 0, 20),
            'direccion' => substr(limpiarCadena(isset($_POST['direccion']) ? $_POST['direccion'] : ''), 0, 180),
            'telefono' => substr(limpiarCadena(isset($_POST['telefono']) ? $_POST['telefono'] : ''), 0, 30),
            'celular' => substr(limpiarCadena(isset($_POST['celular']) ? $_POST['celular'] : ''), 0, 30),
            'correo' => substr(limpiarCadena($correo), 0, 120),
            'web' => substr(limpiarCadena(isset($_POST['web']) ? $_POST['web'] : ''), 0, 120),
            'logo' => substr($logo, 0, 100),
            'color_primario' => colorHexSeguro(isset($_POST['color_primario']) ? $_POST['color_primario'] : '', '#0f766e'),
            'color_secundario' => colorHexSeguro(isset($_POST['color_secundario']) ? $_POST['color_secundario'] : '', '#f59e0b'),
            'serie_boleta' => serieSegura(isset($_POST['serie_boleta']) ? $_POST['serie_boleta'] : '', 'B001'),
            'serie_factura' => serieSegura(isset($_POST['serie_factura']) ? $_POST['serie_factura'] : '', 'F001'),
            'serie_ticket' => serieSegura(isset($_POST['serie_ticket']) ? $_POST['serie_ticket'] : '', 'T001'),
            'serie_cotizacion' => serieSegura(isset($_POST['serie_cotizacion']) ? $_POST['serie_cotizacion'] : '', 'COT'),
            'impuesto_default' => $impuesto,
            'moneda' => $moneda,
            'tipo_negocio' => normalizarPerfilNegocio(isset($_POST['tipo_negocio']) ? $_POST['tipo_negocio'] : ''),
            'dias_alerta_vencimiento' => max(1, min(365, enteroSeguro(isset($_POST['dias_alerta_vencimiento']) ? $_POST['dias_alerta_vencimiento'] : 30) ?: 30)),
            'mensaje_ticket' => substr(limpiarCadena(isset($_POST['mensaje_ticket']) ? $_POST['mensaje_ticket'] : ''), 0, 160)
        );

        $rspta = $empresa->guardar($data);
        if ($rspta) {
            registrarAuditoria('empresa', 'guardar', 'Config empresa: ' . $nombre_comercial . ' moneda ' . $moneda . ' imp ' . $impuesto . ' rubro ' . $data['tipo_negocio'] . ' logo ' . $logo);
            echo 'Configuracion de empresa actualizada correctamente';
        } else {
            echo 'No se pudo actualizar la configuracion';
        }
        break;

    default:
        responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
        break;
}
