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

$pageOne = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$pageTwoA = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$pageTwoB = 'BT /F1 12 Tf 72 704 Td <42> Tj ET';
$phantom = 'BT /F1 12 Tf 72 720 Td (Indirect Contents fallback leak) Tj ET';
$cmapOne = $toUnicodeCMap([
    '41' => 'Indirect Array Page One',
]);
$cmapTwo = $toUnicodeCMap([
    '41' => 'Indirect Array Page Two',
    '42' => 'Shared Resource Still Active',
]);
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 8 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageOne /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOne) . " >>\nstream\n{$pageOne}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($phantom) . " >>\nstream\n{$phantom}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageTwo /Encoding /Identity-H /ToUnicode 12 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($pageTwoA) . " >>\nstream\n{$pageTwoA}\nendstream\nendobj\n"
    . "13 0 obj\n<< /Length " . strlen($pageTwoB) . " >>\nstream\n{$pageTwoB}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($cmapOne) . " >>\nstream\n{$cmapOne}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($cmapTwo) . " >>\nstream\n{$cmapTwo}\nendstream\nendobj\n"
    . "30 0 obj\n[5 0 R]\nendobj\n"
    . "31 0 obj\n[10 0 R 13 0 R]\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expectedLines = [
    'Indirect Array Page One',
    'Indirect Array Page Two',
    'Shared Resource Still Active',
];

echo '<!-- markerpdf-page-contents-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'indirect page /Contents array resolution with page-local /Resources font stacks before Gutenberg paragraph rendering',
    'indirect_contents_arrays_resolved' => $lines === $expectedLines,
    'preserved_page_resource_stack' => ($lines[0] ?? null) === 'Indirect Array Page One'
        && ($lines[1] ?? null) === 'Indirect Array Page Two',
    'excluded_unreferenced_stream_text' => !str_contains($plainText, 'Indirect Contents fallback leak'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
