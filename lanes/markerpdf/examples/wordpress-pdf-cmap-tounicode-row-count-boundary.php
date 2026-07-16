<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fedge 12 Tf 72 720 Td <4142> Tj T* <505152> Tj ET';
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "1 beginbfchar\n"
    . "<41> <0049006D0070006F007200740020>\n"
    . "<42> <004C00650061006B>\n"
    . "endbfchar\n"
    . "1 beginbfrange\n"
    . "<50> <50> <0057>\n"
    . "<51> <52> <0046>\n"
    . "endbfrange\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fedge 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /BoundarySubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-cmap-tounicode-row-count-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-tounicode-bfchar-bfrange-declared-row-count-boundary',
    'support_component' => 'pdf-text-dictionary-core',
    'font_resource' => 'Fedge',
    'declared_bfchar_count_honored' => true,
    'declared_bfrange_count_honored' => true,
    'extra_mapping_rows_ignored' => !str_contains($plainText, 'Leak') && !str_contains($plainText, 'WFG'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
