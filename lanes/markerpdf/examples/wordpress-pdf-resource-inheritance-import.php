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

$pageOne = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET q /FmOne Do Q';
$pageTwo = 'BT /F1 12 Tf 72 720 Td <4142> Tj ET';
$formOne = 'BT /Fform 12 Tf 12 12 Td <43> Tj ET';
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

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R 20 0 R] /Count 2 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 4 0 R >> /XObject << /FmOne 13 0 R >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [8 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
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
    . "15 0 obj\n<< /Length " . strlen($cmapForm) . " >>\nstream\n{$cmapForm}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);

echo '<!-- markerpdf-page-resource-inheritance-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page-tree inherited /Resources font and XObject lookup before Gutenberg paragraph rendering',
    'uses_page_specific_resources' => $lines === ['Inherited One', 'Inherited Form One', 'Inherited Two'],
    'inherits_xobject_resources' => in_array('Inherited Form One', $lines, true),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
