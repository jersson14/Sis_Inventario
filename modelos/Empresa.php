<?php
require "../config/Conexion.php";

class Empresa
{
    public function __construct()
    {
    }

    public function obtener()
    {
        $sql = "SELECT * FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function guardar($data)
    {
        $exist = $this->obtener();

        $nombre_comercial = $data['nombre_comercial'];
        $razon_social = $data['razon_social'];
        $ruc = $data['ruc'];
        $direccion = $data['direccion'];
        $telefono = $data['telefono'];
        $celular = $data['celular'];
        $correo = $data['correo'];
        $web = $data['web'];
        $logo = $data['logo'];
        $color_primario = $data['color_primario'];
        $color_secundario = $data['color_secundario'];
        $serie_boleta = $data['serie_boleta'];
        $serie_factura = $data['serie_factura'];
        $serie_ticket = $data['serie_ticket'];
        $impuesto_default = $data['impuesto_default'];
        $moneda = $data['moneda'];

        if ($exist) {
            $sql = "UPDATE configuracion_empresa SET
                nombre_comercial='$nombre_comercial',
                razon_social='$razon_social',
                ruc='$ruc',
                direccion='$direccion',
                telefono='$telefono',
                celular='$celular',
                correo='$correo',
                web='$web',
                logo='$logo',
                color_primario='$color_primario',
                color_secundario='$color_secundario',
                serie_boleta='$serie_boleta',
                serie_factura='$serie_factura',
                serie_ticket='$serie_ticket',
                impuesto_default='$impuesto_default',
                moneda='$moneda'
                WHERE idconfig='" . $exist['idconfig'] . "'";
            return ejecutarConsulta($sql);
        }

        $sql = "INSERT INTO configuracion_empresa (
            nombre_comercial,razon_social,ruc,direccion,telefono,celular,correo,web,logo,
            color_primario,color_secundario,serie_boleta,serie_factura,serie_ticket,impuesto_default,moneda
        ) VALUES (
            '$nombre_comercial','$razon_social','$ruc','$direccion','$telefono','$celular','$correo','$web','$logo',
            '$color_primario','$color_secundario','$serie_boleta','$serie_factura','$serie_ticket','$impuesto_default','$moneda'
        )";
        return ejecutarConsulta($sql);
    }

    public function datosReporte()
    {
        $decode = function ($value) {
            $txt = trim((string)$value);
            for ($i = 0; $i < 3; $i++) {
                $decoded = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decoded === $txt) {
                    break;
                }
                $txt = $decoded;
            }
            return trim($txt);
        };

        $cfg = $this->obtener();
        if (!$cfg) {
            return array(
                "nombre" => 'PERNO CENTRO "SEÑOR DE HUANCA"',
                "ruc" => '20603558422',
                "direccion_linea1" => 'Bar. Santa Rosa S/N (al costado del Grifo Wari)',
                "direccion_linea2" => 'Abancay - Apurimac',
                "telefono" => '932381391',
                "email" => 'ventas@pernocentro.com',
                "logo" => 'logo1.jpeg',
                "moneda" => 'PEN'
            );
        }

        $direccion = $decode($cfg['direccion']);
        $direccion_linea1 = $direccion;
        $direccion_linea2 = '';
        if ($direccion !== '') {
            $lineas = preg_split('/\r\n|\r|\n/', wordwrap($direccion, 58, "\n", false));
            $direccion_linea1 = isset($lineas[0]) ? trim($lineas[0]) : '';
            $direccion_linea2 = isset($lineas[1]) ? trim($lineas[1]) : '';
        }

        return array(
            "nombre" => $decode(!empty($cfg['razon_social']) ? $cfg['razon_social'] : $cfg['nombre_comercial']),
            "ruc" => $decode($cfg['ruc']),
            "direccion_linea1" => $decode($direccion_linea1),
            "direccion_linea2" => $decode($direccion_linea2),
            "telefono" => $decode(!empty($cfg['telefono']) ? $cfg['telefono'] : $cfg['celular']),
            "email" => $decode($cfg['correo']),
            "logo" => !empty($cfg['logo']) ? $cfg['logo'] : 'logo1.jpeg',
            "moneda" => !empty($cfg['moneda']) ? strtoupper($cfg['moneda']) : 'PEN'
        );
    }
}
?>
