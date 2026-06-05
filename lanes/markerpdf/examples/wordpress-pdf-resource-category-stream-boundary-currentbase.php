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
            throw new RuntimeException('Unable to encode resource-category CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceCategoryStreamBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$propertiesPage = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /BadActual BDC <42> Tj EMC ET q /ValidForm Do Q';
$xobjectPage = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /GoodActual BDC <42> Tj EMC ET q /StreamForm Do Q';
$fontPage = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /DirectForm Do Q';
$validForm = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$streamForm = 'BT /F1 12 Tf 12 24 Td (Stream XObject category form leak) Tj ET';
$directForm = 'BT /Fplain 12 Tf 12 24 Td (Valid direct XObject text) Tj ET';
$propertyPayload = 'BT /F1 12 Tf 1 1 Td (stream property payload leak) Tj ET';
$xobjectPayload = 'BT /F1 12 Tf 1 1 Td (stream xobject category payload leak) Tj ET';
$fontPayload = 'BT /F1 12 Tf 1 1 Td (stream font category payload leak) Tj ET';
$propertiesCMap = $toUnicodeCMap([
    '41' => 'Inherited category font text',
    '42' => 'Visible glyph after stream property',
    '43' => 'Valid inherited category form text',
]);
$xobjectCMap = $toUnicodeCMap([
    '41' => 'XObject category base text',
    '42' => 'Direct property category text',
]);
$fontStreamCMap = $toUnicodeCMap([
    '41' => 'Stream category font leak',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [20 0 R 40 0 R 60 0 R] /Count 3 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 20 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($propertiesPage) . " >>\nstream\n{$propertiesPage}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedCategoryFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($validForm) . " >>\nstream\n{$validForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($propertiesCMap) . " >>\nstream\n{$propertiesCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ValidForm 6 0 R >> /Properties 11 0 R >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($propertyPayload) . " /BadActual << /ActualText (Stream property actual leak) >> >>\nstream\n{$propertyPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [13 0 R] /Count 1 /Resources 30 0 R >>\nendobj\n"
    . "13 0 obj\n<< /Type /Page /Parent 40 0 R /Contents 14 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Length " . strlen($xobjectPage) . " >>\nstream\n{$xobjectPage}\nendstream\nendobj\n"
    . "15 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /InheritedXObjectCategoryFont /Encoding /Identity-H /ToUnicode 18 0 R >>\nendobj\n"
    . "16 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($streamForm) . " >>\nstream\n{$streamForm}\nendstream\nendobj\n"
    . "18 0 obj\n<< /Length " . strlen($xobjectCMap) . " >>\nstream\n{$xobjectCMap}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 15 0 R >> /XObject 31 0 R /Properties << /GoodActual << /ActualText (Direct property category text) >> >> >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($xobjectPayload) . " /StreamForm 16 0 R >>\nstream\n{$xobjectPayload}\nendstream\nendobj\n"
    . "60 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [23 0 R] /Count 1 /Resources 50 0 R >>\nendobj\n"
    . "23 0 obj\n<< /Type /Page /Parent 60 0 R /Contents 24 0 R >>\nendobj\n"
    . "24 0 obj\n<< /Length " . strlen($fontPage) . " >>\nstream\n{$fontPage}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Font 51 0 R /XObject << /DirectForm 52 0 R >> >>\nendobj\n"
    . "51 0 obj\n<< /Length " . strlen($fontPayload) . " /F1 55 0 R >>\nstream\n{$fontPayload}\nendstream\nendobj\n"
    . "52 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources << /Font << /Fplain 53 0 R >> >> /Length " . strlen($directForm) . " >>\nstream\n{$directForm}\nendstream\nendobj\n"
    . "53 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "55 0 obj\n<< /Length " . strlen($fontStreamCMap) . " >>\nstream\n{$fontStreamCMap}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$resourcesByPage = array_map(
    static fn (array $page): array => is_array($page['resources'] ?? null) ? $page['resources'] : [],
    (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf)
);

$expected = [
    'Inherited category font text',
    'Visible glyph after stream property',
    'Valid inherited category form text',
    'XObject category base text',
    'Direct property category text',
    'A',
    'Valid direct XObject text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected page-resource category stream boundary lines before WordPress import.');
}

$flags = [
    'source' => 'native-pdf-page-resource-category-stream-boundary',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Inherited page /Resources category operands that resolve to stream objects are ignored before font lookup, marked-content replacement, and Form XObject expansion',
    'page_count' => count($resourcesByPage),
    'valid_sibling_resource_categories_preserved' => str_contains($plainText, 'Valid inherited category form text')
        && str_contains($plainText, 'Direct property category text')
        && str_contains($plainText, 'Valid direct XObject text'),
    'category_stream_actualtext_promoted' => str_contains($plainText, 'Stream property actual leak'),
    'category_stream_xobject_promoted' => str_contains($plainText, 'Stream XObject category form leak'),
    'category_stream_font_promoted' => str_contains($plainText, 'Stream category font leak'),
    'stream_payload_promoted' => str_contains($plainText, 'stream property payload leak')
        || str_contains($plainText, 'stream xobject category payload leak')
        || str_contains($plainText, 'stream font category payload leak'),
    'properties_category_names' => $resourcesByPage[0]['properties_names'] ?? [],
    'xobject_category_names' => $resourcesByPage[1]['xobject_names'] ?? [],
    'font_category_names' => $resourcesByPage[2]['font_names'] ?? [],
];

echo '<!-- markerpdf:pdf-resource-category-stream-boundary-currentbase ' . htmlspecialchars(json_encode(
    $flags,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
