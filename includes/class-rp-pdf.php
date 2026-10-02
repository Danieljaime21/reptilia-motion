<?php
/**
 * Generador de PDF mínimo (PDF 1.4, Helvetica, WinAnsi) para los presupuestos.
 * Sin dependencias: texto, rectángulos, líneas y una imagen JPEG.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_PDF
{
    /** A4 en puntos */
    public $w = 595.28;
    public $h = 841.89;

    private $pages = [];
    private $current = -1;
    private $images = [];

    /** Anchos de Helvetica / Helvetica-Bold (1/1000 em) para los caracteres 32–126 */
    private static $wReg = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private static $wBold = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    public function addPage()
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function pageCount()
    {
        return count($this->pages);
    }

    private function out($s)
    {
        $this->pages[$this->current] .= $s . "\n";
    }

    private static function enc($text)
    {
        $t = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $text) : utf8_decode((string) $text);
        if ($t === false) {
            $t = preg_replace('/[^\x20-\x7E]/', '?', (string) $text);
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $t);
    }

    private static function rgb($hex)
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    /** Ancho de un texto en puntos */
    public function textWidth($text, $size, $bold = false)
    {
        $t = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $text) : (string) $text;
        $map = $bold ? self::$wBold : self::$wReg;
        $sum = 0;
        $len = strlen((string) $t);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($t[$i]);
            $sum += ($c >= 32 && $c <= 126) ? $map[$c - 32] : 556;
        }
        return $sum * $size / 1000;
    }

    /** Texto con origen arriba-izquierda (y medido desde arriba). align: L | R | C */
    public function text($x, $y, $text, $size = 10, $color = '#111111', $bold = false, $align = 'L')
    {
        if ($align !== 'L') {
            $tw = $this->textWidth($text, $size, $bold);
            $x = $align === 'R' ? $x - $tw : $x - $tw / 2;
        }
        [$r, $g, $b] = self::rgb($color);
        $this->out(sprintf('BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $r, $g, $b, $x, $this->h - $y - $size, self::enc($text)));
    }

    /** Corta un texto en líneas que entren en $maxW */
    public function wrap($text, $size, $maxW, $bold = false)
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim((string) $text)) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($try, $size, $bold) <= $maxW || $line === '') {
                $line = $try;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }

    public function rect($x, $y, $w, $h, $fill = '#000000')
    {
        [$r, $g, $b] = self::rgb($fill);
        $this->out(sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f', $r, $g, $b, $x, $this->h - $y - $h, $w, $h));
    }

    /** Rectángulo con esquinas redondeadas (relleno) */
    public function roundRect($x, $y, $w, $h, $rad, $fill)
    {
        [$r, $g, $b] = self::rgb($fill);
        $k = 0.5523 * $rad;
        $Y = $this->h - $y;
        $p = sprintf('%.3F %.3F %.3F rg ', $r, $g, $b);
        $p .= sprintf('%.2F %.2F m ', $x + $rad, $Y);
        $p .= sprintf('%.2F %.2F l ', $x + $w - $rad, $Y);
        $p .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $w - $rad + $k, $Y, $x + $w, $Y - $rad + $k, $x + $w, $Y - $rad);
        $p .= sprintf('%.2F %.2F l ', $x + $w, $Y - $h + $rad);
        $p .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $w, $Y - $h + $rad - $k, $x + $w - $rad + $k, $Y - $h, $x + $w - $rad, $Y - $h);
        $p .= sprintf('%.2F %.2F l ', $x + $rad, $Y - $h);
        $p .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x + $rad - $k, $Y - $h, $x, $Y - $h + $rad - $k, $x, $Y - $h + $rad);
        $p .= sprintf('%.2F %.2F l ', $x, $Y - $rad);
        $p .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c f', $x, $Y - $rad + $k, $x + $rad - $k, $Y, $x + $rad, $Y);
        $this->out($p);
    }

    public function line($x1, $y1, $x2, $y2, $color = '#DDDDDD', $width = 0.6)
    {
        [$r, $g, $b] = self::rgb($color);
        $this->out(sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S', $r, $g, $b, $width, $x1, $this->h - $y1, $x2, $this->h - $y2));
    }

    /** Imagen JPEG (contenido binario) */
    public function jpeg($data, $x, $y, $w, $h)
    {
        $info = @getimagesizefromstring($data);
        if (!$info) {
            return;
        }
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = ['data' => $data, 'w' => $info[0], 'h' => $info[1], 'cs' => (isset($info['channels']) && $info['channels'] == 1) ? '/DeviceGray' : '/DeviceRGB'];
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $this->h - $y - $h, $name));
    }

    public function output()
    {
        $objs = [];
        $add = function ($body) use (&$objs) {
            $objs[] = $body;
            return count($objs);
        };
        $catalog = $add(null);
        $pagesId = $add(null);
        $f1 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2 = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $xobj = '';
        foreach ($this->images as $name => $im) {
            $id = $add(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $im['w'], $im['h'], $im['cs'], strlen($im['data']), $im['data']));
            $xobj .= "/$name $id 0 R ";
        }
        $res = sprintf('<< /Font << /F1 %d 0 R /F2 %d 0 R >> /XObject << %s>> >>', $f1, $f2, $xobj);
        $kids = [];
        foreach ($this->pages as $content) {
            $c = $add(sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content));
            $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', $pagesId, $this->w, $this->h, $res, $c));
        }
        $objs[$catalog - 1] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesId);
        $objs[$pagesId - 1] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', array_map(fn($k) => "$k 0 R", $kids)), count($kids));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF", count($objs) + 1, $catalog, $xref);
        return $pdf;
    }
}
