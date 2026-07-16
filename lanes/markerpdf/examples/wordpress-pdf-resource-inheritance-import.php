<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$toUnicodeCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode CMap fixture text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageOne = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET q /FmOne Do Q q /FmLegacyOuter Do Q q /FmExplicit Do Q';
$pageTwo = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET';
$escapedTypePage = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /EscapedForm Do Q';
$formOne = 'BT /Fform 12 Tf 12 12 Td <43> Tj ET';
$legacyOuter = 'q /FmLegacyNested Do Q';
$legacyNested = 'BT /Fplain 12 Tf 12 12 Td (Legacy Nested Form Resources) Tj ET';
$explicitForm = 'q /FmLegacyNested Do Q BT /Fplain 12 Tf 12 12 Td (Explicit Form Local Resources) Tj ET';
$escapedTypeForm = 'BT /F1 12 Tf 12 12 Td (Escaped Type Form Resources) Tj ET';
$cmapOne = $toUnicodeCMap([
    '41' => 'Inherited',
    '42' => ' One',
]);
$cmapTwo = $toUnicodeCMap([
    '41' => 'Inherited',
    '42' => ' Two',
]);
$cmapForm = $toUnicodeCMap([
    '43' => 'Inherited Form One',
]);
$escapedTypeCmap = $toUnicodeCMap([
    '41' => 'Escaped Type Font Resources',
]);
$privateCmap = $toUnicodeCMap([
    '41' => 'Private',
    '42' => ' Leak',
]);
$privateForm = 'BT /F1 12 Tf 12 12 Td (Private Nested Resource Leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R 30 0 R] /Count 3 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources << /Properties << /Private << /Font << /F1 21 0 R >> /XObject << /FmOne 23 0 R >> >> >> /Font << /F1 4 0 R /Fplain 16 0 R >> /XObject << /FmOne 13 0 R /FmLegacyOuter 17 0 R /FmLegacyNested 18 0 R /FmExplicit 19 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /Pa#67es /Parent 2 0 R /Kids [24 0 R] /Count 1 /Resources 28 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 10 0 R /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedOne /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedTwo /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /Fform 14 0 R >> >> /Length " . strlen($formOne) . " >>\nstream\n{$formOne}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedForm /Encoding /Identity-H /ToUnicode 15 0 R >>\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($cmapForm) . " >>\nstream\n{$cmapForm}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "17 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($legacyOuter) . " >>\nstream\n{$legacyOuter}\nendstream\nendobj\n"
    . "18 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($legacyNested) . " >>\nstream\n{$legacyNested}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /Fplain 16 0 R >> >> /Length " . strlen($explicitForm) . " >>\nstream\n{$explicitForm}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PrivateNested /Encoding /Identity-H /ToUnicode 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Length " . strlen($privateCmap) . " >>\nstream\n{$privateCmap}\nendstream\nendobj\n"
    . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
    . "24 0 obj\n<< /Type /P#61ge /Parent 30 0 R /Contents 25 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($escapedTypePage) . " >>\nstream\n{$escapedTypePage}\nendstream\nendobj\n"
    . "26 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedTypeFont /Encoding /Identity-H /ToUnicode 27 0 R >>\nendobj\n"
    . "27 0 obj\n<< /Length " . strlen($escapedTypeCmap) . " >>\nstream\n{$escapedTypeCmap}\nendstream\nendobj\n"
    . "28 0 obj\n<< /Font << /F1 26 0 R >> /XObject << /EscapedForm 29 0 R >> >>\nendobj\n"
    . "29 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($escapedTypeForm) . " >>\nstream\n{$escapedTypeForm}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$expectedLines = [
    'Inherited One',
    'Inherited Form One',
    'Legacy Nested Form Resources',
    'Explicit Form Local Resources',
    'Inherited Two',
    'Escaped Type Font Resources',
    'Escaped Type Form Resources',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected page-resource inheritance smoke lines to match current-base legacy Form behavior.');
}

echo '<!-- markerpdf-page-resource-inheritance-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page-tree inherited /Resources font and XObject lookup plus legacy Form XObject omitted-Resources fallback before Gutenberg paragraph rendering',
    'uses_page_specific_resources' => $lines === $expectedLines,
    'top_level_resource_categories_ignore_nested_decoys' => !str_contains(implode("\n", $lines), 'Private'),
    'inherits_xobject_resources' => in_array('Inherited Form One', $lines, true),
    'inherits_legacy_form_page_resources' => in_array('Legacy Nested Form Resources', $lines, true),
    'keeps_explicit_form_resources_unmerged' => substr_count(implode("\n", $lines), 'Legacy Nested Form Resources') === 1,
    'decodes_escaped_page_tree_type_names' => in_array('Escaped Type Font Resources', $lines, true)
        && in_array('Escaped Type Form Resources', $lines, true)
        && count($lines) === 7,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
