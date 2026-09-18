<?php
/**
 * Importe en letras para comprobantes: 605.50 -> "SEISCIENTOS CINCO CON 50/100 SOLES".
 *
 * Reemplaza la clase heredada, que en PHP 8 repetia cada palabra
 * ("SEISCIENTOSSEISCIENTOS"): comparaba '' == 0, que ya no es verdadero.
 * Se conserva la clase EnLetras y su metodo ValorEnLetras($monto, $moneda)
 * para no tocar a quien la usa.
 */
class EnLetras
{
    /** Se mantiene por compatibilidad; "mil" siempre se escribe sin "un". */
    public $substituir_un_mil_por_mil = true;

    public function ValorEnLetras($x, $Moneda_singular, $Moneda_plural = "", $Centesima_parte_singular = "", $Centesima_parte_plural = "")
    {
        $moneda = trim((string)$Moneda_singular);
        $monto = round(abs((float)$x), 2);
        $entero = (int)floor($monto);
        $centimos = (int)round(($monto - $entero) * 100);
        if ($centimos === 100) {
            $entero++;
            $centimos = 0;
        }

        $texto = $entero === 0 ? 'cero' : self::numero($entero);
        $texto .= ' con ' . str_pad((string)$centimos, 2, '0', STR_PAD_LEFT) . '/100';
        if ($moneda !== '') {
            $texto .= ' ' . $moneda;
        }
        if ((float)$x < 0) {
            $texto = 'menos ' . $texto;
        }
        return mb_strtoupper($texto, 'UTF-8');
    }

    /** Entero positivo en palabras, con "un" apocopado (veintiún, ciento un). */
    public static function numero($n)
    {
        $n = (int)$n;
        if ($n >= 1000000000000) {
            return (string)$n;
        }
        $partes = array();
        $billones = intdiv($n, 1000000000);
        $millones = intdiv($n % 1000000000, 1000000);
        $miles = intdiv($n % 1000000, 1000);
        $resto = $n % 1000;
        if ($billones > 0) {
            // mil millones
            $partes[] = ($billones === 1 ? 'mil' : self::miles($billones) . ' mil') . ($millones === 0 ? ' millones' : '');
        }
        if ($millones > 0) {
            $partes[] = $millones === 1 && $billones === 0 ? 'un millón' : self::miles($millones) . ' millones';
        }
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'mil' : self::cientos($miles) . ' mil';
        }
        if ($resto > 0) {
            $partes[] = self::cientos($resto);
        }
        return implode(' ', $partes);
    }

    /** 1 a 999999 */
    private static function miles($n)
    {
        $m = intdiv($n, 1000);
        $r = $n % 1000;
        $txt = $m === 0 ? '' : ($m === 1 ? 'mil' : self::cientos($m) . ' mil');
        if ($r > 0) {
            $txt .= ($txt === '' ? '' : ' ') . self::cientos($r);
        }
        return $txt;
    }

    /** 1 a 999 */
    private static function cientos($n)
    {
        $centenas = array('', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos');
        if ($n === 100) {
            return 'cien';
        }
        $c = intdiv($n, 100);
        $r = $n % 100;
        $txt = $centenas[$c];
        if ($r > 0) {
            $txt .= ($txt === '' ? '' : ' ') . self::decenas($r);
        }
        return $txt;
    }

    /** 1 a 99 */
    private static function decenas($n)
    {
        $unidades = array('', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte', 'veintiún', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve');
        if ($n < 30) {
            return $unidades[$n];
        }
        $decenas = array(3 => 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa');
        $d = intdiv($n, 10);
        $u = $n % 10;
        return $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
    }
}
