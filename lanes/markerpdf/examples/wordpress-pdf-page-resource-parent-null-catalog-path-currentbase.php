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
            throw new RuntimeException('Unable to encode parent-null catalog-path smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceParentNullCatalogPathCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ParentNullForm Do Q q /DetachedForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td <42> Tj ET';
$detachedForm = 'BT /F1 12 Tf 12 24 Td (Detached null-parent form leak) Tj ET';
$cMap = $toUnicodeCMap([
    '41' => 'Direct parent null catalog font text',
    '42' => 'Direct parent null catalog form text',
]);
$detachedCMap = $toUnicodeCMap([
    '41' => 'Detached null-parent font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 20 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent null /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentNullSmokeFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DetachedNullSmokeFont /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentNullForm 7 0 R >> >>\nendobj\n"
    . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
    . "40 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /DetachedForm 9 0 R >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Direct parent null catalog font text',
    'Direct parent null catalog form text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected selected catalog-path resources to render WordPress paragraphs for /Parent null.');
}

if (($resources['resource_owner_object'] ?? null) !== 10 || ($resources['resource_object'] ?? null) !== 20) {
    throw new RuntimeException('Expected page-resource review metadata to use the selected catalog Kids path.');
}

echo '<!-- markerpdf-page-resource-parent-null-catalog-path-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-parent-null-catalog-path-currentbase',
    'native_boundary' => 'page /Parent null is treated like omitted Parent for selected catalog Kids path resource inheritance, without accepting detached parent resources',
    'catalog_path_resources_selected' => ($resources['resource_owner_object'] ?? null) === 10,
    'parent_null_font_decoded' => $lines[0] === 'Direct parent null catalog font text',
    'parent_null_form_expanded' => $lines[1] === 'Direct parent null catalog form text',
    'detached_parent_resources_excluded' => !str_contains($plainText, 'Detached null-parent font leak')
        && !str_contains($plainText, 'Detached null-parent form leak'),
    'visible_paragraph_count' => count($lines),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
