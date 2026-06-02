<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fcid 12 Tf 72 720 Td <202122230041> Tj ET';
$derivedCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/BaseCycleGuardCMap usecmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "<0000> <00FF>\n"
    . "endcodespacerange\n"
    . "4 beginbfchar\n"
    . "<20> <0049006D0070006F00720074>\n"
    . "<23> <0021>\n"
    . "<00> <0020>\n"
    . "<41> <004F004B>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /DerivedCycleGuardCMap defineresource pop\n"
    . "/CMapName /DerivedCycleGuardCMap def\n"
    . "end\n"
    . "end\n";
$baseCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/DerivedCycleGuardCMap usecmap\n"
    . "2 beginbfchar\n"
    . "<21> <0020>\n"
    . "<22> <0042006C006F0063006B0073>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /BaseCycleGuardCMap defineresource pop\n"
    . "/CMapName /BaseCycleGuardCMap def\n"
    . "end\n"
    . "end\n";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fcid 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CycleGuardSubset /Encoding /Identity-H /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($derivedCMap) . " >>\nstream\n{$derivedCMap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($baseCMap) . " >>\nstream\n{$baseCMap}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-cmap-usecmap-cycle-codespace-guard ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-tounicode-usecmap-cycle-codespace-count',
    'support_component' => 'pdf-text-dictionary-core',
    'font_resource' => 'Fcid',
    'cycle_guarded' => true,
    'declared_codespace_count_honored' => true,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
