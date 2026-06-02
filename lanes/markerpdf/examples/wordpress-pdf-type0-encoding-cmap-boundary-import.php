<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPMixedBoundary-H def\n"
    . "2 begincodespacerange\n"
    . "<20> <7F>\n"
    . "<0000> <00FF>\n"
    . "endcodespacerange\n"
    . "2 begincidrange\n"
    . "<0057> <0065> 200\n"
    . "<42> <74> 300\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Fcid 12 Tf 1 0 0 1 72 720 Tm <0057006900640065> Tj 1 0 0 1 118 720 Tm <426C6F636B> Tj '
    . 'T* 1 0 0 1 72 704 Tm <5468696E> Tj 1 0 0 1 96 704 Tm <0054006500780074> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /MixedBoundarySubset /Encoding 3 0 R /DescendantFonts [4 0 R] >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /MixedBoundarySubset /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 500 /W [84 120 250 200 214 1000 300 352 250] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type0-encoding-cmap-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type0-encoding-cmap-code-space-width-boundary',
    'font_width_sources' => ['Type0 /Encoding CMap begincodespacerange', 'Type0 /Encoding CMap begincidrange', 'Descendant CIDFont /W'],
    'no_tounicode_fallback' => true,
    'uses_encoding_cmap_code_space_boundaries' => $plainText === "WideBlock\nThin Text",
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
