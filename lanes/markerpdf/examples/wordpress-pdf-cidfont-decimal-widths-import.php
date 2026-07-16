<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<0000> <FFFF>\n"
    . "endcodespacerange\n"
    . "17 beginbfchar\n"
    . "<0001> <0057>\n"
    . "<0002> <0069>\n"
    . "<0003> <0064>\n"
    . "<0004> <0065>\n"
    . "<0005> <0042>\n"
    . "<0006> <006C>\n"
    . "<0007> <006F>\n"
    . "<0008> <0063>\n"
    . "<0009> <006B>\n"
    . "<0014> <0044>\n"
    . "<0015> <0061>\n"
    . "<0016> <0074>\n"
    . "<0017> <0061>\n"
    . "<0018> <0046>\n"
    . "<0019> <006C>\n"
    . "<001A> <006F>\n"
    . "<001B> <0077>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0001000200030004> Tj 1 0 0 1 118 720 Tm <00050006000700080009> Tj '
    . 'T* 1 0 0 1 72 704 Tm <0014001500160017> Tj 1 0 0 1 118 704 Tm <00180019001A001B> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CIDDecimalWidthSubset /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /CIDDecimalWidthSubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [1 [1000.5 1000.5 1000.5 1000.5 1000.5 1000.5 1000.5 1000.5 1000.5] 20 27 1000.5] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-cidfont-decimal-widths ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-cidfont-decimal-w-widths-to-unicode-boundary',
    'font_width_sources' => ['CIDFont /W array numeric widths', 'CIDFont /W range numeric widths', 'ToUnicode CMap source codes'],
    'decimal_widths_preserve_joined_blocks' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'DataFlow'),
    'integer_fragment_fallback_excluded' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'Data Flow'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
