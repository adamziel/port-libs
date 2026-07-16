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
            throw new RuntimeException('Unable to encode indirect resource wrapper CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceIndirectWrapperCurrentBaseCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Fwrapped 12 Tf 72 720 Td <41> Tj T* /Span /WrappedActual BDC <42> Tj EMC ET q /WrappedForm Do Q';
$formContent = 'BT /Fwrapped 12 Tf 12 24 Td <43> Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Wrapped inherited font text',
    '42' => 'Wrapped inherited actual text glyph',
    '43' => 'Wrapped inherited form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 12 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WrappedInheritedFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /ActualText (Wrapped inherited actual text) >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font 14 0 R /XObject 16 0 R /Properties 18 0 R >>\nendobj\n"
    . "12 0 obj\n10 0 R\nendobj\n"
    . "14 0 obj\n15 0 R\nendobj\n"
    . "15 0 obj\n<< /Fwrapped 5 0 R >>\nendobj\n"
    . "16 0 obj\n17 0 R\nendobj\n"
    . "17 0 obj\n<< /WrappedForm 6 0 R >>\nendobj\n"
    . "18 0 obj\n19 0 R\nendobj\n"
    . "19 0 obj\n<< /WrappedActual 7 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Wrapped inherited font text',
    'Wrapped inherited actual text',
    'Wrapped inherited form text',
];

if ($lines !== $expected || ($resources['resource_object'] ?? null) !== 10) {
    throw new RuntimeException('Expected inherited indirect resource wrappers to resolve before WordPress rendering.');
}

echo '<!-- markerpdf-page-resource-indirect-wrapper-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-indirect-wrapper-currentbase',
    'native_boundary' => 'page /Resources and category dictionaries unwrap exact indirect references before text extraction',
    'indirect_resource_wrapper_resolved' => $lines === $expected,
    'final_resource_dictionary_object' => $resources['resource_object'] ?? null,
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'font_category_wrapper_resolved' => ($resources['font_names'] ?? []) === ['Fwrapped'],
    'xobject_category_wrapper_resolved' => ($resources['xobject_names'] ?? []) === ['WrappedForm'],
    'properties_category_wrapper_resolved' => ($resources['properties_names'] ?? []) === ['WrappedActual'],
    'raw_resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'Fwrapped')
        && !str_contains($plainText, 'WrappedForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
