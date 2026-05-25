<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fcid 12 Tf 72 720 Td <202122> Tj ET';
$baseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<20> <22>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<20> <0049006D0070006F00720074>\n"
    . "<22> <0042006C006F0063006B0073>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /BaseImportCMap defineresource pop\n"
    . "/CMapName /BaseImportCMap def\n"
    . "end\n"
    . "end\n";
$fontCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/BaseImportCMap usecmap\n"
    . "1 beginbfchar\n"
    . "<21> <0020>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /DerivedImportCMap defineresource pop\n"
    . "end\n"
    . "end\n";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /UseCMapSubset /Encoding /Identity-H /ToUnicode 4 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($fontCMap) . " >>\nstream\n{$fontCMap}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-cmap-usecmap ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-tounicode-usecmap',
    'support_component' => 'pdf-text-dictionary-core',
    'font_resource' => 'Fcid',
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
