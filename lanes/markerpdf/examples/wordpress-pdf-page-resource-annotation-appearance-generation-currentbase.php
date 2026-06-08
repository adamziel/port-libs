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
            throw new RuntimeException('Unable to encode WordPress annotation appearance generation CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressAnnotationAppearanceGenerationCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageContent = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
$staleAppearance = 'BT /F1 10 Tf 0 18 Td <43> Tj ET';
$currentAppearance = 'BT /F1 10 Tf 0 18 Td <42> Tj ET';
$staleOnlyAppearance = 'BT /F1 10 Tf 0 18 Td <44> Tj ET';
$cMap = $toUnicodeCMap([
    '41' => 'WordPress page body',
    '42' => 'Current appearance annotation',
    '43' => 'Stale same-number appearance leak',
    '44' => 'Stale missing-generation appearance leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [20 0 R 21 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WordPressAppearanceGeneration /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 660 240 700] /AP << /N 30 1 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 620 240 650] /AP << /N 31 1 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($staleAppearance) . " >>\nstream\n{$staleAppearance}\nendstream\nendobj\n"
    . "30 1 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($currentAppearance) . " >>\nstream\n{$currentAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($staleOnlyAppearance) . " >>\nstream\n{$staleOnlyAppearance}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'WordPress page body',
    'Current appearance annotation',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected generation-exact annotation appearance import for WordPress paragraphs.');
}

if (($resources['resource_owner_object'] ?? null) !== 2 || ($resources['resource_object'] ?? null) !== 10) {
    throw new RuntimeException('Expected annotation appearance smoke to use inherited page resources.');
}

echo '<!-- markerpdf-page-resource-annotation-appearance-generation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-annotation-appearance-generation-currentbase',
    'native_boundary' => 'annotation appearance /AP and /N references are generation-exact before inherited page resources are used for WordPress text import',
    'inherited_page_resources_resolved' => ($resources['resource_owner_object'] ?? null) === 2
        && ($resources['resource_object'] ?? null) === 10
        && ($resources['font_names'] ?? null) === ['F1'],
    'valid_nonzero_generation_appearance_imported' => in_array('Current appearance annotation', $lines, true),
    'stale_same_number_generation_rejected' => !str_contains($plainText, 'Stale same-number appearance leak'),
    'stale_missing_generation_rejected' => !str_contains($plainText, 'Stale missing-generation appearance leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
