<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideEncoding = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /DirectWide-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<01> <09> 40\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$thinEncoding = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /DirectThin-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 begincidrange\n"
    . "<01> <09> 60\n"
    . "endcidrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "9 beginbfchar\n"
    . "<01> <0054>\n"
    . "<02> <0068>\n"
    . "<03> <0069>\n"
    . "<04> <006E>\n"
    . "<05> <0054>\n"
    . "<06> <0065>\n"
    . "<07> <0078>\n"
    . "<08> <0074>\n"
    . "<09> <0021>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$content = 'BT /Fthin 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
    . '1 0 0 1 96 720 Tm <05060708> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << "
    . "/Fwide << /Type /Font /Subtype /Type0 /BaseFont /DirectWide /Encoding 3 0 R /DescendantFonts [4 0 R] /ToUnicode 7 0 R >> "
    . "/Fthin << /Type /Font /Subtype /Type0 /BaseFont /DirectThin /Encoding 5 0 R /DescendantFonts [6 0 R] /ToUnicode 7 0 R >> "
    . ">> >> /Contents 8 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideEncoding) . " >>\nstream\n{$wideEncoding}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectWide /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /FontDescriptor 9 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($thinEncoding) . " >>\nstream\n{$thinEncoding}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DirectThin /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 250 /FontDescriptor 10 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /FontDescriptor /FontName /DirectWideSerif /Flags 34 >>\nendobj\n"
    . "10 0 obj\n<< /Type /FontDescriptor /FontName /DirectThinSerif /Flags 34 >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-type0-cmap-descriptor-width-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-direct-type0-resource-cmap-descriptor-width-boundary',
    'font_width_sources' => ['direct page /Resources /Font dictionary', 'Type0 /Encoding CMap', 'Descendant CIDFont /DW', 'FontDescriptor /Flags'],
    'direct_type0_resource_resolved' => $plainText === 'Thin Text',
    'selected_resource_font' => $firstSpan['font'] ?? null,
    'wrong_fallback_font_excluded' => ($firstSpan['font'] ?? null) !== 'DirectWideSerif_serif_non_symbolic',
    'control_source_bytes_excluded' => preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plainText) !== 1,
    'current_base_gap_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
