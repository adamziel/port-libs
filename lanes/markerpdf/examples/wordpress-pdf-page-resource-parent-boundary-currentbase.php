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
            throw new RuntimeException('Unable to encode page Parent resource boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceParentBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedForm Do Q';
$currentForm = 'BT /F1 12 Tf 12 24 Td (Top level parent form text) Tj ET';
$privateForm = 'BT /F1 12 Tf 12 24 Td (Nested decoy parent form leak) Tj ET';
$currentCMap = $toUnicodeCMap([
    '41' => 'Top level parent font text',
]);
$privateCMap = $toUnicodeCMap([
    '41' => 'Nested decoy parent font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /PieceInfo << /WPReview << /Private << /Parent 99 0 R >> /ReviewOnly true >> >> /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /TopLevelParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($currentCMap) . " >>\nstream\n{$currentCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($currentForm) . " >>\nstream\n{$currentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NestedPrivateParentFont /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($privateForm) . " >>\nstream\n{$privateForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedForm 7 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($privateCMap) . " >>\nstream\n{$privateCMap}\nendstream\nendobj\n"
    . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 8 0 R >> /XObject << /InheritedForm 9 0 R >> >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$expected = [
    'Top level parent font text',
    'Top level parent form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected top-level page /Parent resource inheritance to exclude nested private parent decoys.');
}

$plainText = implode("\n", $lines);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];

echo '<!-- markerpdf-page-resource-parent-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'top-level page /Parent resolves inherited /Resources while nested private /Parent keys remain review-only metadata',
    'top_level_parent_selected' => $lines === $expected,
    'nested_parent_decoy_excluded' => !str_contains($plainText, 'Nested decoy parent'),
    'inherited_form_selected' => in_array('Top level parent form text', $lines, true),
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'resource_inherited' => $resourceMetadata['inherited'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
