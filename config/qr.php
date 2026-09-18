<?php
/**
 * Generador de codigos QR sin dependencias (modo byte, correccion de errores
 * nivel M, versiones 1 a 20: hasta ~660 bytes). Sigue la norma ISO/IEC 18004
 * con el mismo algoritmo que la implementacion de referencia de Nayuki.
 *
 *   qrMatriz($texto)             matriz de bool (true = modulo negro)
 *   qrSvg($texto, $margen = 4)   SVG listo para imprimir (nitido en ticketera)
 *   qrDibujarPdf($pdf, $texto, $x, $y, $lado)   lo dibuja en un FPDF
 *
 * No necesita internet: la tienda puede imprimir el ticket sin conexion.
 */

/** Codewords de correccion por bloque y numero de bloques (nivel M), por version. */
function qrTablaM()
{
    return array(
        'ecc' => array(-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26),
        'bloques' => array(-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16)
    );
}

/** Modulos disponibles para datos + correccion (sin patrones fijos). */
function qrModulosDatos($ver)
{
    $r = (16 * $ver + 128) * $ver + 64;
    if ($ver >= 2) {
        $numAlign = intdiv($ver, 7) + 2;
        $r -= (25 * $numAlign - 10) * $numAlign - 55;
        if ($ver >= 7) {
            $r -= 36;
        }
    }
    return $r;
}

function qrCodewordsDatos($ver)
{
    $t = qrTablaM();
    return intdiv(qrModulosDatos($ver), 8) - $t['ecc'][$ver] * $t['bloques'][$ver];
}

/** Multiplicacion en GF(2^8) con el polinomio 0x11D. */
function qrMul($x, $y)
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
}

function qrDivisorRs($grado)
{
    $res = array_fill(0, $grado, 0);
    $res[$grado - 1] = 1;
    $raiz = 1;
    for ($i = 0; $i < $grado; $i++) {
        for ($j = 0; $j < $grado; $j++) {
            $res[$j] = qrMul($res[$j], $raiz);
            if ($j + 1 < $grado) {
                $res[$j] ^= $res[$j + 1];
            }
        }
        $raiz = qrMul($raiz, 0x02);
    }
    return $res;
}

function qrRestoRs(array $datos, array $divisor)
{
    $res = array_fill(0, count($divisor), 0);
    foreach ($datos as $b) {
        $factor = $b ^ array_shift($res);
        $res[] = 0;
        foreach ($divisor as $i => $coef) {
            $res[$i] ^= qrMul($coef, $factor);
        }
    }
    return $res;
}

function qrPosicionesAlineacion($ver)
{
    if ($ver === 1) {
        return array();
    }
    $tam = $ver * 4 + 17;
    $numAlign = intdiv($ver, 7) + 2;
    $paso = ($ver === 32) ? 26 : (int)ceil(($ver * 4 + 4) / ($numAlign * 2 - 2)) * 2;
    $res = array(6);
    for ($pos = $tam - 7; count($res) < $numAlign; $pos -= $paso) {
        array_splice($res, 1, 0, array($pos));
    }
    return $res;
}

/**
 * Matriz del QR para $texto (UTF-8, modo byte).
 * @return bool[][] $m[y][x]
 */
function qrMatriz($texto)
{
    $bytes = array_values(unpack('C*', (string)$texto) ?: array());
    $t = qrTablaM();

    // Version minima que admite el texto
    $ver = 0;
    for ($v = 1; $v <= 20; $v++) {
        $bitsConteo = $v <= 9 ? 8 : 16;
        if (4 + $bitsConteo + count($bytes) * 8 <= qrCodewordsDatos($v) * 8) {
            $ver = $v;
            break;
        }
    }
    if ($ver === 0) {
        throw new InvalidArgumentException('Texto demasiado largo para el QR');
    }

    // Flujo de bits: modo byte (0100), longitud, datos, terminador y relleno
    $bits = array();
    $agregar = function ($valor, $n) use (&$bits) {
        for ($i = $n - 1; $i >= 0; $i--) {
            $bits[] = ($valor >> $i) & 1;
        }
    };
    $agregar(0x4, 4);
    $agregar(count($bytes), $ver <= 9 ? 8 : 16);
    foreach ($bytes as $b) {
        $agregar($b, 8);
    }
    $capacidad = qrCodewordsDatos($ver) * 8;
    $agregar(0, min(4, $capacidad - count($bits)));
    $agregar(0, (8 - count($bits) % 8) % 8);
    for ($pad = 0xEC; count($bits) < $capacidad; $pad ^= 0xEC ^ 0x11) {
        $agregar($pad, 8);
    }
    $datos = array();
    for ($i = 0; $i < count($bits); $i += 8) {
        $b = 0;
        for ($j = 0; $j < 8; $j++) {
            $b = ($b << 1) | $bits[$i + $j];
        }
        $datos[] = $b;
    }

    // Bloques con su correccion Reed-Solomon, intercalados
    $numBloques = $t['bloques'][$ver];
    $eccLen = $t['ecc'][$ver];
    $totalCw = intdiv(qrModulosDatos($ver), 8);
    $numCortos = $numBloques - $totalCw % $numBloques;
    $largoCorto = intdiv($totalCw, $numBloques);
    $divisor = qrDivisorRs($eccLen);
    $bloques = array();
    $k = 0;
    for ($i = 0; $i < $numBloques; $i++) {
        $n = $largoCorto - $eccLen + ($i < $numCortos ? 0 : 1);
        $dat = array_slice($datos, $k, $n);
        $k += $n;
        $ecc = qrRestoRs($dat, $divisor);
        if ($i < $numCortos) {
            $dat[] = 0;
        }
        $bloques[] = array_merge($dat, $ecc);
    }
    $final = array();
    $largoBloque = count($bloques[0]);
    for ($i = 0; $i < $largoBloque; $i++) {
        foreach ($bloques as $j => $bloque) {
            if ($i !== $largoCorto - $eccLen || $j >= $numCortos) {
                $final[] = $bloque[$i];
            }
        }
    }

    // Patrones fijos
    $tam = $ver * 4 + 17;
    $m = array_fill(0, $tam, array_fill(0, $tam, false));
    $fijo = array_fill(0, $tam, array_fill(0, $tam, false));
    $poner = function ($x, $y, $negro) use (&$m, &$fijo) {
        $m[$y][$x] = (bool)$negro;
        $fijo[$y][$x] = true;
    };
    for ($i = 0; $i < $tam; $i++) {
        $poner(6, $i, $i % 2 === 0);
        $poner($i, 6, $i % 2 === 0);
    }
    foreach (array(array(3, 3), array($tam - 4, 3), array(3, $tam - 4)) as $c) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $xx = $c[0] + $dx;
                $yy = $c[1] + $dy;
                if ($xx >= 0 && $xx < $tam && $yy >= 0 && $yy < $tam) {
                    $d = max(abs($dx), abs($dy));
                    $poner($xx, $yy, $d !== 2 && $d !== 4);
                }
            }
        }
    }
    $pos = qrPosicionesAlineacion($ver);
    $na = count($pos);
    for ($i = 0; $i < $na; $i++) {
        for ($j = 0; $j < $na; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $poner($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
    }
    $formato = function ($mascara) use ($poner, $tam) {
        $dato = (0 << 3) | $mascara; // nivel M = 00
        $rem = $dato;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $b = (($dato << 10) | $rem) ^ 0x5412;
        $bit = function ($i) use ($b) { return (($b >> $i) & 1) === 1; };
        for ($i = 0; $i <= 5; $i++) {
            $poner(8, $i, $bit($i));
        }
        $poner(8, 7, $bit(6));
        $poner(8, 8, $bit(7));
        $poner(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $poner(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $poner($tam - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $poner(8, $tam - 15 + $i, $bit($i));
        }
        $poner(8, $tam - 8, true);
    };
    $formato(0);
    if ($ver >= 7) {
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $b = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $negro = (($b >> $i) & 1) === 1;
            $a = $tam - 11 + $i % 3;
            $c = intdiv($i, 3);
            $poner($a, $c, $negro);
            $poner($c, $a, $negro);
        }
    }

    // Datos en zigzag
    $i = 0;
    $totalBits = count($final) * 8;
    for ($der = $tam - 1; $der >= 1; $der -= 2) {
        if ($der === 6) {
            $der = 5;
        }
        for ($vert = 0; $vert < $tam; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $der - $j;
                $subiendo = (($der + 1) & 2) === 0;
                $y = $subiendo ? $tam - 1 - $vert : $vert;
                if (!$fijo[$y][$x] && $i < $totalBits) {
                    $m[$y][$x] = (($final[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                    $i++;
                }
            }
        }
    }

    // Mascara con menor penalizacion
    $aplicar = function ($mascara) use (&$m, $fijo, $tam) {
        for ($y = 0; $y < $tam; $y++) {
            for ($x = 0; $x < $tam; $x++) {
                if ($fijo[$y][$x]) {
                    continue;
                }
                switch ($mascara) {
                    case 0: $inv = ($x + $y) % 2 === 0; break;
                    case 1: $inv = $y % 2 === 0; break;
                    case 2: $inv = $x % 3 === 0; break;
                    case 3: $inv = ($x + $y) % 3 === 0; break;
                    case 4: $inv = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $inv = $x * $y % 2 + $x * $y % 3 === 0; break;
                    case 6: $inv = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
                    default: $inv = (($x + $y) % 2 + $x * $y % 3) % 2 === 0; break;
                }
                if ($inv) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
    };
    $mejor = 0;
    $menor = PHP_INT_MAX;
    for ($mascara = 0; $mascara < 8; $mascara++) {
        $aplicar($mascara);
        $formato($mascara);
        $p = qrPenalizacion($m);
        if ($p < $menor) {
            $menor = $p;
            $mejor = $mascara;
        }
        $aplicar($mascara); // XOR: vuelve al original
    }
    $aplicar($mejor);
    $formato($mejor);
    return $m;
}

/** Penalizacion de la norma (reglas 1 a 4) para elegir la mascara. */
function qrPenalizacion(array $m)
{
    $tam = count($m);
    $p = 0;
    $oscuros = 0;
    $patronA = array(1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0);
    $patronB = array(0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1);
    for ($eje = 0; $eje < 2; $eje++) {
        for ($a = 0; $a < $tam; $a++) {
            $linea = array();
            for ($b = 0; $b < $tam; $b++) {
                $linea[] = $eje === 0 ? ($m[$a][$b] ? 1 : 0) : ($m[$b][$a] ? 1 : 0);
            }
            $racha = 1;
            for ($b = 1; $b <= $tam; $b++) {
                if ($b < $tam && $linea[$b] === $linea[$b - 1]) {
                    $racha++;
                    continue;
                }
                if ($racha >= 5) {
                    $p += 3 + ($racha - 5);
                }
                $racha = 1;
            }
            for ($b = 0; $b + 11 <= $tam; $b++) {
                $trozo = array_slice($linea, $b, 11);
                if ($trozo === $patronA || $trozo === $patronB) {
                    $p += 40;
                }
            }
        }
    }
    for ($y = 0; $y < $tam; $y++) {
        for ($x = 0; $x < $tam; $x++) {
            if ($m[$y][$x]) {
                $oscuros++;
            }
            if ($x + 1 < $tam && $y + 1 < $tam) {
                $c = $m[$y][$x];
                if ($m[$y][$x + 1] === $c && $m[$y + 1][$x] === $c && $m[$y + 1][$x + 1] === $c) {
                    $p += 3;
                }
            }
        }
    }
    $total = $tam * $tam;
    $k = (int)ceil(abs($oscuros * 20 - $total * 10) / $total) - 1;
    return $p + max(0, $k) * 10;
}

/** SVG del QR; cada modulo mide 1 unidad y el tamano real lo pone el CSS. */
function qrSvg($texto, $margen = 4)
{
    $m = qrMatriz($texto);
    $tam = count($m);
    $lado = $tam + $margen * 2;
    $d = '';
    for ($y = 0; $y < $tam; $y++) {
        for ($x = 0; $x < $tam; $x++) {
            if (!$m[$y][$x]) {
                continue;
            }
            // Une los modulos negros seguidos de la fila en un solo rectangulo
            $ini = $x;
            while ($x + 1 < $tam && $m[$y][$x + 1]) {
                $x++;
            }
            $d .= 'M' . ($ini + $margen) . ' ' . ($y + $margen) . 'h' . ($x - $ini + 1) . 'v1h-' . ($x - $ini + 1) . 'z';
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lado . ' ' . $lado . '" shape-rendering="crispEdges" role="img" aria-label="Código QR">'
        . '<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $d . '"/></svg>';
}

/** Dibuja el QR en un FPDF (sin margen: dejar espacio blanco alrededor). */
function qrDibujarPdf($pdf, $texto, $x, $y, $lado)
{
    $m = qrMatriz($texto);
    $tam = count($m);
    $mod = $lado / $tam;
    $pdf->SetFillColor(0, 0, 0);
    for ($fy = 0; $fy < $tam; $fy++) {
        for ($fx = 0; $fx < $tam; $fx++) {
            if (!$m[$fy][$fx]) {
                continue;
            }
            $ini = $fx;
            while ($fx + 1 < $tam && $m[$fy][$fx + 1]) {
                $fx++;
            }
            $pdf->Rect($x + $ini * $mod, $y + $fy * $mod, ($fx - $ini + 1) * $mod, $mod, 'F');
        }
    }
}
