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
            throw new RuntimeException('Unable to encode escaped-Kids CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressEscapedKidsResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceEscapedKidsPdf = static function (bool $indirectKids = false) use ($toUnicodeCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /InheritedKidsForm Do Q';
    $formContent = 'BT /F1 12 Tf 12 24 Td (Escaped Kids inherited form text) Tj ET';
    $decoyContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET';
    $cmap = $toUnicodeCMap([
        '41' => $indirectKids ? 'Escaped indirect Kids inherited text' : 'Escaped Kids inherited font text',
        '42' => 'Nested decoy kid resource leak',
    ]);
    $kidsValue = $indirectKids ? '20 0 R' : '[3 0 R]';
    $kidsArrayObject = $indirectKids ? "20 0 obj\n[3 0 R]\nendobj\n" : '';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /PieceInfo << /WPReview << /Private << /Kids [99 0 R] /ReviewOnly true >> >> >> /Type /Pages /Ki#64s {$kidsValue} /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /EscapedKidsInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /InheritedKidsForm 7 0 R >> >>\nendobj\n"
        . "99 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 100 0 R >>\nendobj\n"
        . "100 0 obj\n<< /Length " . strlen($decoyContent) . " >>\nstream\n{$decoyContent}\nendstream\nendobj\n"
        . $kidsArrayObject
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$pdf = $pageResourceEscapedKidsPdf(false);
$indirectPdf = $pageResourceEscapedKidsPdf(true);
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$indirectLines = $extractor->extractTextLines($indirectPdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Escaped Kids inherited font text',
    'Escaped Kids inherited form text',
];

if ($lines !== $expectedLines || $indirectLines !== ['Escaped indirect Kids inherited text', 'Escaped Kids inherited form text']) {
    throw new RuntimeException('Expected escaped page-tree Kids traversal before inherited resource WordPress import.');
}

echo '<!-- markerpdf-page-resource-escaped-kids-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-escaped-kids-currentbase',
    'native_boundary' => 'escaped top-level /Kids page-tree traversal before inherited /Resources font and XObject lookup',
    'escaped_top_level_kids_selected' => $lines === $expectedLines,
    'nested_decoy_kids_excluded' => !str_contains($plainText, 'Nested decoy kid resource leak'),
    'indirect_escaped_kids_array_resolved' => $indirectLines === ['Escaped indirect Kids inherited text', 'Escaped Kids inherited form text'],
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
