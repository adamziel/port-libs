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
            throw new RuntimeException('Unable to encode page-resource kid generation CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /KidGenerationResourceCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pdfForKids = static function (string $kids, string $validPage = '') use ($toUnicodeCMap): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td <42> Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $toUnicodeCMap([
        '41' => 'Current kid generation inherited text',
        '42' => 'Stale kid generation resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids {$kids} /Count 2 /Resources 10 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /KidGenerationInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . $validPage
        . "10 0 obj\n<< /Font << /F1 5 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$currentContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$validPage = "8 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream\nendobj\n";

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$pdf = $pdfForKids('[3 1 R 8 0 R]', $validPage);
$allStalePdf = $pdfForKids('[3 1 R]');
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$allStaleLines = $extractor->extractTextLines($allStalePdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];
$expectedLines = ['Current kid generation inherited text'];

if ($lines !== $expectedLines || $allStaleLines !== []) {
    throw new RuntimeException('Expected generation-exact page-tree Kids traversal before inherited resource lookup.');
}

echo '<!-- markerpdf-page-resource-kid-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-kid-generation-currentbase',
    'native_boundary' => 'page-tree /Kids references are generation-exact before inherited /Resources font lookup and fallback stream scanning',
    'valid_sibling_inherits_resources' => $lines === $expectedLines,
    'stale_kid_generation_excluded' => !str_contains($plainText, 'Stale kid generation'),
    'all_stale_page_tree_blocks_fallback' => $allStaleLines === [],
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'resource_inherited' => $resourceMetadata['inherited'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
