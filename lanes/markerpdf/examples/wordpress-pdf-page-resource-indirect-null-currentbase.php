<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode indirect-null resource CMap text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /IndirectNullResourcesCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageOneContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedNullForm Do Q';
$pageTwoContent = 'q /InheritedNullForm Do Q';
$inheritedForm = 'BT /F1 12 Tf 12 24 Td (Indirect null inherited form text) Tj ET';
$cmap = $toUnicodeCMap('Indirect null inherited font text');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 12 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 13 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /IndirectNullInherited /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($inheritedForm) . " >>\nstream\n{$inheritedForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /InheritedNullForm 9 0 R >> >>\nendobj\n"
    . "12 0 obj\nnull\nendobj\n"
    . "13 0 obj\n<< >>\nendobj\n"
    . "%%EOF";

$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$expectedLines = [
    'Indirect null inherited font text',
    'Indirect null inherited form text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected indirect-null page resource inheritance smoke lines to match.');
}

$firstResources = $boundary[0]['resources'] ?? [];
$secondResources = $boundary[1]['resources'] ?? [];
$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'indirect null page /Resources inheritance with explicit empty resource dictionaries blocking parent resource lookup before Gutenberg paragraph rendering',
    'indirect_null_resources_inherit_parent' => ($firstResources['inherited'] ?? null) === true
        && ($firstResources['resource_owner_object'] ?? null) === 2
        && ($firstResources['resource_object'] ?? null) === 10,
    'inherited_font_decoded' => in_array('Indirect null inherited font text', $lines, true),
    'inherited_xobject_expanded_once' => substr_count($plainText, 'Indirect null inherited form text') === 1,
    'explicit_empty_resources_block_parent_xobject' => ($secondResources['inherited'] ?? null) === false
        && ($secondResources['resource_object'] ?? null) === 13
        && ($secondResources['categories'] ?? null) === [],
];

echo '<!-- markerpdf-page-resource-indirect-null-smoke ' . htmlspecialchars(json_encode(
    $flags,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
