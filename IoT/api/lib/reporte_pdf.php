<?php
declare(strict_types=1);

final class ReportePdf
{
    private array $paginas = [];
    private array $imagenes = [];

    public function nuevaPagina(): void
    {
        $this->paginas[] = [];
    }

    public function rect(float $x, float $y, float $ancho, float $alto, string $color): void
    {
        $this->agregar(sprintf('%s rg %.2F %.2F %.2F %.2F re f', $color, $x, $y, $ancho, $alto));
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, string $color, float $grosor = 1): void
    {
        $this->agregar(sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S', $color, $grosor, $x1, $y1, $x2, $y2));
    }

    public function texto(float $x, float $y, float $tamano, string $texto, string $color = '0 0 0', bool $negritas = false): void
    {
        $fuente = $negritas ? 'F2' : 'F1';
        $this->agregar(sprintf(
            'BT /%s %.2F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $fuente,
            $tamano,
            $color,
            $x,
            $y,
            $this->escaparTexto($texto)
        ));
    }

    public function imagenPng(string $ruta, float $x, float $y, float $ancho, float $alto, array $fondo): bool
    {
        $clave = $ruta . '|' . implode(',', $fondo);
        if (!isset($this->imagenes[$clave])) {
            $imagen = $this->decodificarPngRgba($ruta, $fondo);
            if ($imagen === null) {
                return false;
            }
            $imagen['nombre'] = 'Im' . (count($this->imagenes) + 1);
            $this->imagenes[$clave] = $imagen;
        }

        $imagen = $this->imagenes[$clave];
        $this->agregar(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q',
            $ancho,
            $alto,
            $x,
            $y,
            $imagen['nombre']
        ));
        return true;
    }

    public function salida(): string
    {
        if ($this->paginas === []) {
            $this->nuevaPagina();
        }

        $objetos = [
            1 => '',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $idsPagina = [];
        $idsImagenes = [];

        foreach ($this->imagenes as $clave => $imagen) {
            $idImagen = count($objetos) + 1;
            $idsImagenes[$clave] = $idImagen;
            $objetos[$idImagen] = '<< /Type /XObject /Subtype /Image'
                . ' /Width ' . $imagen['ancho']
                . ' /Height ' . $imagen['alto']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
                . ' /Filter /FlateDecode /Length ' . strlen($imagen['datos']) . " >>\nstream\n"
                . $imagen['datos'] . "\nendstream";
        }

        $recursosImagenes = '';
        foreach ($this->imagenes as $clave => $imagen) {
            $recursosImagenes .= '/' . $imagen['nombre'] . ' ' . $idsImagenes[$clave] . ' 0 R ';
        }

        foreach ($this->paginas as $comandos) {
            $contenido = implode("\n", $comandos) . "\n";
            $idContenido = count($objetos) + 1;
            $objetos[$idContenido] = "<< /Length " . strlen($contenido) . " >>\nstream\n{$contenido}endstream";
            $idPagina = count($objetos) + 1;
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89]'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'
                . ($recursosImagenes === '' ? '' : ' /XObject << ' . $recursosImagenes . ' >>')
                . ' >>'
                . " /Contents {$idContenido} 0 R >>";
            $idsPagina[] = $idPagina;
        }

        $hijos = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $idsPagina));
        $objetos[2] = '<< /Type /Pages /Count ' . count($idsPagina) . " /Kids [{$hijos}] >>";
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        ksort($objetos);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objetos as $id => $objeto) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$objeto}\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
        foreach (array_keys($objetos) as $id) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF";
        return $pdf;
    }

    private function agregar(string $comando): void
    {
        if ($this->paginas === []) {
            $this->nuevaPagina();
        }
        $ultima = array_key_last($this->paginas);
        $this->paginas[$ultima][] = $comando;
    }

    private function escaparTexto(string $texto): string
    {
        $convertido = function_exists('iconv')
            ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto)
            : $texto;
        $convertido = $convertido === false ? $texto : $convertido;
        $convertido = preg_replace('/[^\x20-\x7E\x80-\xFF]/', ' ', $convertido) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $convertido);
    }

    private function decodificarPngRgba(string $ruta, array $fondo): ?array
    {
        $contenido = @file_get_contents($ruta);
        if ($contenido === false || substr($contenido, 0, 8) !== "\x89PNG\r\n\x1A\n") {
            return null;
        }

        $offset = 8;
        $cabecera = null;
        $idat = '';
        $longitudContenido = strlen($contenido);
        while ($offset + 12 <= $longitudContenido) {
            $longitud = unpack('Nlongitud', substr($contenido, $offset, 4))['longitud'];
            $tipo = substr($contenido, $offset + 4, 4);
            $datos = substr($contenido, $offset + 8, $longitud);
            $offset += 12 + $longitud;
            if ($tipo === 'IHDR') {
                $cabecera = $datos;
            } elseif ($tipo === 'IDAT') {
                $idat .= $datos;
            } elseif ($tipo === 'IEND') {
                break;
            }
        }

        if ($cabecera === null || strlen($cabecera) !== 13 || $idat === '') {
            return null;
        }
        $info = unpack('Nancho/Nalto/Cprofundidad/Ccolor/Ccompresion/Cfiltro/Centrelazado', $cabecera);
        if (
            $info['profundidad'] !== 8
            || $info['color'] !== 6
            || $info['entrelazado'] !== 0
            || $info['ancho'] < 1
            || $info['alto'] < 1
        ) {
            return null;
        }

        $crudo = @gzuncompress($idat);
        if ($crudo === false) {
            return null;
        }
        $bytesLinea = $info['ancho'] * 4;
        if (strlen($crudo) < ($bytesLinea + 1) * $info['alto']) {
            return null;
        }

        $rojoFondo = max(0, min(255, (int) ($fondo[0] ?? 18)));
        $verdeFondo = max(0, min(255, (int) ($fondo[1] ?? 22)));
        $azulFondo = max(0, min(255, (int) ($fondo[2] ?? 27)));
        $previo = array_fill(0, $bytesLinea, 0);
        $salida = '';
        $indice = 0;

        for ($fila = 0; $fila < $info['alto']; $fila++) {
            $tipoFiltro = ord($crudo[$indice++]);
            $linea = array_fill(0, $bytesLinea, 0);
            for ($columna = 0; $columna < $bytesLinea; $columna++) {
                $valor = ord($crudo[$indice++]);
                $izquierda = $columna >= 4 ? $linea[$columna - 4] : 0;
                $arriba = $previo[$columna];
                $arribaIzquierda = $columna >= 4 ? $previo[$columna - 4] : 0;
                if ($tipoFiltro === 1) {
                    $valor = ($valor + $izquierda) & 255;
                } elseif ($tipoFiltro === 2) {
                    $valor = ($valor + $arriba) & 255;
                } elseif ($tipoFiltro === 3) {
                    $valor = ($valor + intdiv($izquierda + $arriba, 2)) & 255;
                } elseif ($tipoFiltro === 4) {
                    $prediccion = $izquierda + $arriba - $arribaIzquierda;
                    $pa = abs($prediccion - $izquierda);
                    $pb = abs($prediccion - $arriba);
                    $pc = abs($prediccion - $arribaIzquierda);
                    $valor = ($valor + ($pa <= $pb && $pa <= $pc ? $izquierda : ($pb <= $pc ? $arriba : $arribaIzquierda))) & 255;
                } elseif ($tipoFiltro !== 0) {
                    return null;
                }
                $linea[$columna] = $valor;
            }

            $rgbLinea = '';
            for ($columna = 0; $columna < $bytesLinea; $columna += 4) {
                $alpha = $linea[$columna + 3];
                if ($alpha === 255) {
                    $rgbLinea .= chr($linea[$columna]) . chr($linea[$columna + 1]) . chr($linea[$columna + 2]);
                    continue;
                }
                $inverso = 255 - $alpha;
                $rgbLinea .= chr(intdiv($linea[$columna] * $alpha + $rojoFondo * $inverso, 255));
                $rgbLinea .= chr(intdiv($linea[$columna + 1] * $alpha + $verdeFondo * $inverso, 255));
                $rgbLinea .= chr(intdiv($linea[$columna + 2] * $alpha + $azulFondo * $inverso, 255));
            }
            $salida .= $rgbLinea;
            $previo = $linea;
        }

        return [
            'ancho' => $info['ancho'],
            'alto' => $info['alto'],
            'datos' => gzcompress($salida, 6),
        ];
    }
}

function reportePdfColor(string $estado): string
{
    $estado = strtoupper($estado);
    if (in_array($estado, ['CRITICO', 'ALARMA', 'FALLO', 'OFFLINE', 'PRECAUCION', 'REVISAR', 'CALENTANDO'], true)) {
        return '1 0.69 0';
    }
    return '0.18 0.18 0.18';
}

function reportePdfTextoCorto(?string $texto, int $maximo = 32): string
{
    $texto = trim((string) $texto);
    if (strlen($texto) <= $maximo) {
        return $texto;
    }
    return substr($texto, 0, max(0, $maximo - 3)) . '...';
}
