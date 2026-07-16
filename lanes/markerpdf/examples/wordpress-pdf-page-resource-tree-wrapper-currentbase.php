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
            throw new RuntimeException('Unable to encode page-resource tree-wrapper CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /PageResourceTreeWrapperCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceTreeWrapperPdf = static function () use ($toUnicodeCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /WrappedForm Do Q';
    $form = 'BT /F1 12 Tf 12 24 Td (Wrapped page-tree inherited form text) Tj ET';
    $cmap = $toUnicodeCMap([
        '41' => 'Wrapped page-tree inherited font text',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 12 0 R >>\nendobj\n"
        . "12 0 obj\n2 0 R\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [13 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
        . "13 0 obj\n3 0 R\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WrappedPageTreeInherited /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /WrappedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$pdf = $pageResourceTreeWrapperPdf();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);
$resourceMetadata = $boundary[0]['resources'] ?? [];
$expected = [
    'Wrapped page-tree inherited font text',
    'Wrapped page-tree inherited form text',
];

if ($lines !== $expected || ($resourceMetadata['resource_owner_object'] ?? null) !== 2) {
    throw new RuntimeException('Expected wrapped page-tree references to preserve inherited page resources.');
}

echo '<!-- markerpdf-page-resource-tree-wrapper-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-tree-wrapper-currentbase',
    'native_boundary' => 'catalog /Pages and page-tree /Kids wrapper references resolve generation-exactly before inherited /Resources font and XObject lookup',
    'catalog_pages_wrapper_resolved' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'kids_wrapper_resolved' => $lines === $expected,
    'resource_owner_object' => $resourceMetadata['resource_owner_object'] ?? null,
    'resource_object' => $resourceMetadata['resource_object'] ?? null,
    'resource_inherited' => $resourceMetadata['inherited'] ?? null,
    'font_names' => $resourceMetadata['font_names'] ?? [],
    'xobject_names' => $resourceMetadata['xobject_names'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
