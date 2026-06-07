<?php
if (strlen(session_id()) < 1) {
    session_start();
}

require_once "../modelos/Empresa.php";

$empresa = new Empresa();

switch ($_GET['op']) {
    case 'publicBrand':
        $rspta = $empresa->obtener();
        if (!$rspta) {
            echo json_encode(array(
                'nombre_comercial' => 'PERNO CENTRO',
                'razon_social' => 'SEÑOR DE HUANCA',
                'color_primario' => '#0f766e',
                'color_secundario' => '#f59e0b',
                'logo_url' => 'logo1.jpeg',
                'moneda' => 'PEN',
                'simbolo_moneda' => 'S/'
            ));
            break;
        }

        $logoUrl = 'logo1.jpeg';
        if (!empty($rspta['logo'])) {
            $logoEmpresaFS = realpath(__DIR__ . '/../files/empresa/' . $rspta['logo']);
            if ($logoEmpresaFS && file_exists($logoEmpresaFS)) {
                $logoUrl = '../files/empresa/' . $rspta['logo'];
            } elseif (file_exists(__DIR__ . '/../vistas/' . $rspta['logo'])) {
                $logoUrl = $rspta['logo'];
            }
        }

        echo json_encode(array(
            'nombre_comercial' => !empty($rspta['nombre_comercial']) ? $rspta['nombre_comercial'] : 'PERNO CENTRO',
            'razon_social' => !empty($rspta['razon_social']) ? $rspta['razon_social'] : 'SEÑOR DE HUANCA',
            'color_primario' => !empty($rspta['color_primario']) ? $rspta['color_primario'] : '#0f766e',
            'color_secundario' => !empty($rspta['color_secundario']) ? $rspta['color_secundario'] : '#f59e0b',
            'logo_url' => $logoUrl,
            'moneda' => !empty($rspta['moneda']) ? strtoupper($rspta['moneda']) : 'PEN',
            'simbolo_moneda' => obtenerSimboloMoneda(!empty($rspta['moneda']) ? strtoupper($rspta['moneda']) : 'PEN')
        ));
        break;

    case 'defaults':
        $rspta = $empresa->obtener();
        if (!$rspta) {
            echo json_encode(array(
                'serie_boleta' => 'B001',
                'serie_factura' => 'F001',
                'serie_ticket' => 'T001',
                'impuesto_default' => '18.00',
                'moneda' => 'PEN',
                'simbolo_moneda' => 'S/'
            ));
            break;
        }
        echo json_encode(array(
            'serie_boleta' => $rspta['serie_boleta'],
            'serie_factura' => $rspta['serie_factura'],
            'serie_ticket' => $rspta['serie_ticket'],
            'impuesto_default' => $rspta['impuesto_default'],
            'moneda' => !empty($rspta['moneda']) ? strtoupper($rspta['moneda']) : 'PEN',
            'simbolo_moneda' => obtenerSimboloMoneda(!empty($rspta['moneda']) ? strtoupper($rspta['moneda']) : 'PEN')
        ));
        break;

    case 'mostrar':
        $rspta = $empresa->obtener();
        echo json_encode($rspta ? $rspta : array());
        break;

    case 'guardaryeditar':
        if (!isset($_SESSION['acceso']) || (int)$_SESSION['acceso'] !== 1) {
            echo "No tienes permiso para actualizar la configuracion.";
            break;
        }

        $logo = isset($_POST['logoactual']) ? limpiarCadena($_POST['logoactual']) : '';

        if (isset($_FILES['logo']) && file_exists($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $ext = explode('.', $_FILES['logo']['name']);
            $tipo = $_FILES['logo']['type'];
            if ($tipo == 'image/jpg' || $tipo == 'image/jpeg' || $tipo == 'image/png' || $tipo == 'image/webp') {
                $logo = round(microtime(true)) . '.' . strtolower(end($ext));
                if (!is_dir('../files/empresa')) {
                    mkdir('../files/empresa', 0777, true);
                }
                move_uploaded_file($_FILES['logo']['tmp_name'], '../files/empresa/' . $logo);
            }
        }

        $data = array(
            'nombre_comercial' => limpiarCadena(isset($_POST['nombre_comercial']) ? $_POST['nombre_comercial'] : ''),
            'razon_social' => limpiarCadena(isset($_POST['razon_social']) ? $_POST['razon_social'] : ''),
            'ruc' => limpiarCadena(isset($_POST['ruc']) ? $_POST['ruc'] : ''),
            'direccion' => limpiarCadena(isset($_POST['direccion']) ? $_POST['direccion'] : ''),
            'telefono' => limpiarCadena(isset($_POST['telefono']) ? $_POST['telefono'] : ''),
            'celular' => limpiarCadena(isset($_POST['celular']) ? $_POST['celular'] : ''),
            'correo' => limpiarCadena(isset($_POST['correo']) ? $_POST['correo'] : ''),
            'web' => limpiarCadena(isset($_POST['web']) ? $_POST['web'] : ''),
            'logo' => $logo,
            'color_primario' => limpiarCadena(isset($_POST['color_primario']) ? $_POST['color_primario'] : '#0f766e'),
            'color_secundario' => limpiarCadena(isset($_POST['color_secundario']) ? $_POST['color_secundario'] : '#f59e0b'),
            'serie_boleta' => limpiarCadena(isset($_POST['serie_boleta']) ? $_POST['serie_boleta'] : 'B001'),
            'serie_factura' => limpiarCadena(isset($_POST['serie_factura']) ? $_POST['serie_factura'] : 'F001'),
            'serie_ticket' => limpiarCadena(isset($_POST['serie_ticket']) ? $_POST['serie_ticket'] : 'T001'),
            'impuesto_default' => limpiarCadena(isset($_POST['impuesto_default']) ? $_POST['impuesto_default'] : '18.00'),
            'moneda' => limpiarCadena(isset($_POST['moneda']) ? $_POST['moneda'] : 'PEN')
        );

        if ($data['nombre_comercial'] === '') {
            echo 'El nombre comercial es obligatorio.';
            break;
        }

        $rspta = $empresa->guardar($data);
        echo $rspta ? 'Configuracion de empresa actualizada correctamente' : 'No se pudo actualizar la configuracion';
        break;
}
?>
