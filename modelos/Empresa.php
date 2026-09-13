<?php
/**
 * Modelo de configuracion de empresa (una sola fila en configuracion_empresa).
 * Consultas preparadas.
 */
require_once "../config/Conexion.php";

class Empresa
{
    /** Campos editables desde el formulario, en el orden del INSERT/UPDATE. */
    const CAMPOS = array(
        'nombre_comercial', 'razon_social', 'ruc', 'direccion', 'telefono', 'celular', 'correo', 'web', 'logo',
        'color_primario', 'color_secundario', 'serie_boleta', 'serie_factura', 'serie_ticket',
        'impuesto_default', 'moneda', 'mensaje_ticket'
    );

    public function __construct()
    {
    }

    public function obtener()
    {
        return dbRow("SELECT * FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
    }

    /**
     * Inserta o actualiza la configuracion. $data debe traer las claves de
     * self::CAMPOS (las que falten se rellenan con cadena vacia o default).
     */
    public function guardar($data)
    {
        $defaults = array(
            'color_primario' => '#0f766e',
            'color_secundario' => '#f59e0b',
            'serie_boleta' => 'B001',
            'serie_factura' => 'F001',
            'serie_ticket' => 'T001',
            'impuesto_default' => 18.00,
            'moneda' => 'PEN',
            'mensaje_ticket' => 'Gracias por su compra'
        );

        $valores = array();
        foreach (self::CAMPOS as $campo) {
            if ($campo === 'impuesto_default') {
                $valores[] = isset($data[$campo]) ? round((float)$data[$campo], 2) : (float)$defaults[$campo];
                continue;
            }
            $v = isset($data[$campo]) ? trim((string)$data[$campo]) : '';
            if ($v === '' && isset($defaults[$campo])) {
                $v = (string)$defaults[$campo];
            }
            $valores[] = $v;
        }

        $exist = $this->obtener();
        if ($exist) {
            $sets = array();
            foreach (self::CAMPOS as $campo) {
                $sets[] = $campo . '=?';
            }
            $valores[] = (int)$exist['idconfig'];
            $sql = "UPDATE configuracion_empresa SET " . implode(', ', $sets) . " WHERE idconfig=?";
            return dbExec($sql, $valores);
        }

        $sql = "INSERT INTO configuracion_empresa (" . implode(', ', self::CAMPOS) . ") VALUES (" . implode(', ', array_fill(0, count(self::CAMPOS), '?')) . ")";
        return dbInsert($sql, $valores) > 0;
    }

    /**
     * Datos normalizados para cabeceras de reportes/tickets.
     * Decodifica entidades HTML (los textos se guardan escapados).
     */
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
                "nombre" => 'Mi Tienda',
                "nombre_comercial" => 'Mi Tienda',
                "ruc" => '',
                "direccion_linea1" => '',
                "direccion_linea2" => '',
                "telefono" => '',
                "email" => '',
                "web" => '',
                "logo" => 'logo1.jpeg',
                "moneda" => 'PEN',
                "mensaje_ticket" => 'Gracias por su compra'
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

        $nombreComercial = $decode($cfg['nombre_comercial']);
        return array(
            "nombre" => $decode(!empty($cfg['razon_social']) ? $cfg['razon_social'] : $cfg['nombre_comercial']),
            "nombre_comercial" => $nombreComercial,
            "ruc" => $decode($cfg['ruc']),
            "direccion_linea1" => $decode($direccion_linea1),
            "direccion_linea2" => $decode($direccion_linea2),
            "telefono" => $decode(!empty($cfg['telefono']) ? $cfg['telefono'] : $cfg['celular']),
            "email" => $decode($cfg['correo']),
            "web" => $decode($cfg['web']),
            "logo" => !empty($cfg['logo']) ? $cfg['logo'] : 'logo1.jpeg',
            "moneda" => !empty($cfg['moneda']) ? strtoupper($cfg['moneda']) : 'PEN',
            "mensaje_ticket" => $decode(!empty($cfg['mensaje_ticket']) ? $cfg['mensaje_ticket'] : 'Gracias por su compra')
        );
    }
}
