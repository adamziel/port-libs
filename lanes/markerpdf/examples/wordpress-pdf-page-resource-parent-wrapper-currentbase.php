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
            throw new RuntimeException('Unable to encode page-resource parent-wrapper CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceParentWrapperCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /ParentWrapperForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td <42> Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Parent wrapper inherited font text',
    '42' => 'Parent wrapper inherited form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentWrapperFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentWrapperForm 7 0 R >> >>\nendobj\n"
    . "20 0 obj\n2 0 R\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Parent wrapper inherited font text',
    'Parent wrapper inherited form text',
];

$flags = [
    'source' => 'native-pdf-page-resource-parent-wrapper-currentbase',
    'native_boundary' => 'page /Parent wrapper references resolve generation-exactly to the real /Pages node before inherited /Resources lookup',
    'parent_wrapper_resolved' => $lines === $expected,
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'wrapper_object_not_resource_owner' => ($resources['resource_owner_object'] ?? null) !== 20,
    'visible_text_excludes_resource_names' => !str_contains($plainText, 'ParentWrapperForm')
        && !str_contains($plainText, 'WordPressPageResourceParentWrapperCMap'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $flags['parent_wrapper_resolved'] !== true
    || $flags['resource_owner_object'] !== 2
    || $flags['resource_object'] !== 10
    || $flags['resource_inherited'] !== true
    || $flags['visible_text_excludes_resource_names'] !== true
) {
    throw new RuntimeException('Expected page /Parent wrapper resources to render as WordPress paragraphs.');
}

echo '<!-- markerpdf-page-resource-parent-wrapper-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
