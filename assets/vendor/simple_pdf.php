<?php
/**
 * Minimal PDF generator for LifeMed receipts.
 * Built-in Helvetica-like font using raw PDF font metrics.
 * No external dependencies.
 */
class SimplePdf {
    private $pages = [];
    private $current = '';
    private $fontUsed = false;

    // Helvetica base14 metrics (subset for latin+cyrillic)
    private $fontDef = [
        'Type' => '/Font',
        'Subtype' => '/Type1',
        'BaseFont' => '/Helvetica',
        'Encoding' => '/WinAnsiEncoding',
    ];

    public function __construct() {
        $this->addPage();
    }

    public function addPage() {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function setFont($family = 'helvetica', $style = '', $size = 10) {
        // Store for text rendering
        $this->_font = strtolower($family);
        $this->_style = strtoupper($style);
        $this->_size = $size;
    }

    public function cell($w, $h = 5, $txt = '', $border = 0, $ln = 0, $align = 'L') {
        $y = $this->_y ?? 10;
        $x = $this->_x ?? 10;

        $alignMap = ['L' => 0, 'C' => 1, 'R' => 2];
        $a = $alignMap[strtoupper($align)] ?? 0;

        // Escape text for PDF
        $txt = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
        // Replace Cyrillic with WinAnsiEncoding equivalents (cp1251 mapping in PDF)
        $txt = $this->encodeForPdf($txt);

        $fs = $this->_size ?? 10;
        $bold = (strpos($this->_style, 'B') !== false) ? 'Bold' : '';
        // Use Helvetica for text

        $op = 'BT';
        $line = sprintf('%.1f %.1f Td', $x, 297 - $y - $fs);
        $font = sprintf('/%s%s %s Tf', 'Helvetica', $bold ? '-Bold' : '', $fs);
        $this->pages[$this->current] .= "$op\n$line\n$font\n($txt) Tj\nET\n";

        if ($w > 0) {
            $this->_x = $x + $w;
        }
        if ($ln == 1) {
            $this->_x = 10;
            $this->_y = ($this->_y ?? 10) + $h;
        } elseif ($ln == 2) {
            $this->_x = 10;
            $this->_y = ($this->_y ?? 10) + $h;
        }
    }

    private function encodeForPdf($text) {
        // WinAnsiEncoding supports ISO-8859-1. For Cyrillic, we need a workaround.
        // Since PDF Type1 Helvetica doesn't contain Cyrillic glyphs,
        // we'll use a simple transliteration approach for basic display.
        $cyrToLat = [
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo',
            'Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M',
            'Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U',
            'Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sch',
            'Ъ'=>'"','Ы'=>'Y','Ь'=>'\'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'"','ы'=>'y','ь'=>'\'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        return strtr($text, $cyrToLat);
    }

    public function ln($h = 0) {
        $this->_x = 10;
        if ($h > 0) {
            $this->_y = ($this->_y ?? 10) + $h;
        } else {
            $this->_y = ($this->_y ?? 10) + ($this->_size ?? 10) * 0.4;
        }
    }

    public function getX() { return $this->_x ?? 10; }
    public function getY() { return $this->_y ?? 10; }
    public function setX($x) { $this->_x = $x; }
    public function setXY($x, $y) { $this->_x = $x; $this->_y = $y; }

    public function Output($dest, $name) {
        // Build PDF
        $objects = [];
        $objNum = 1;

        // Obj 1: Catalog
        $objects[$objNum] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $catalogNum = $objNum++;

        // Obj 2: Pages
        $pageRefs = '';
        foreach ($this->pages as $i => $content) {
            $objNum++;
            $pageRefs .= sprintf('%d 0 R ', $objNum);
            // Page content stream
            $stream = "stream\n" . $content . "endstream\n";
            $objects[$objNum] = sprintf(
                "%d 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 210 297] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R >> >> >>\nendobj\n",
                $objNum, $objNum, $objNum + 1
            );
            $contentNum = $objNum;

            // Font object
            $objNum++;
            $fontDef = "/Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding";
            $objects[$objNum] = sprintf("%d 0 obj\n<< %s >>\nendobj\n", $objNum, $fontDef);
            $fontNum = $objNum;
        }

        // Rebuild obj 2 (Pages)
        $objects[2] = sprintf("2 0 obj\n<< /Type /Pages /Kids [%s] /Count %d >>\nendobj\n", $pageRefs, count($this->pages));

        // Build xref
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $xref = "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= max(array_keys($objects)); $i++) {
            if (isset($offsets[$i])) {
                $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $xref .= "0000000000 00000 f \n";
            }
        }

        $trailer = "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";
        $pdf .= $xref . $trailer;

        if ($dest === 'F') {
            file_put_contents($name, $pdf);
            return $name;
        }
        return $pdf;
    }
}
