<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
            throw new RuntimeException('Unable to encode page resource Kids token-boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceKidsTokenCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$decoyContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET q /DecoyForm Do Q';
$currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /CurrentForm Do Q';
$decoyForm = 'BT /F1 12 Tf 12 24 Td (Nested Kids decoy form leak) Tj ET';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Top-level Kids inherited form text) Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Top-level Kids inherited font text',
    '42' => 'Nested Kids decoy font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [<< /Private [3 0 R] >> [99 0 R] 5 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidsTokenSmokeFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($decoyForm) . " >>\nstream\n{$decoyForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /XObject << /CurrentForm 9 0 R /DecoyForm 11 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Top-level Kids inherited font text',
    'Top-level Kids inherited form text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected only top-level page-tree Kids references to produce WordPress paragraphs.');
}

if (($outline['pages'] ?? null) !== 1 || count($boundary) !== 1) {
    throw new RuntimeException('Expected nested Kids payload references to stay out of the page count and review rows.');
}

if (str_contains($plainText, 'Nested Kids decoy font leak') || str_contains($plainText, 'Nested Kids decoy form leak')) {
    throw new RuntimeException('Nested Kids payload references leaked into WordPress paragraph text.');
}

echo '<!-- markerpdf-page-resource-kids-token-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-kids-token-boundary-currentbase',
    'native_boundary' => 'page-tree /Kids accepts only top-level indirect references before inherited /Resources drive visible WordPress text',
    'page_count' => $outline['pages'] ?? 0,
    'review_page_count' => count($boundary),
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'visible_lines' => $lines,
    'nested_payload_text_excluded' => !str_contains($plainText, 'Nested Kids decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
