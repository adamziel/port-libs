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
            throw new RuntimeException('Unable to encode catalog parent boundary CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CatalogParentBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pdfWithDetachedParent = static function () use ($toUnicodeCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET q /DetachedForm Do Q';
    $detachedForm = 'BT /F1 12 Tf 12 24 Td (Detached parent form leak) Tj ET';
    $detachedCMap = $toUnicodeCMap([
        '41' => 'Detached parent font leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [10 0 R] /Count 1 >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Parent 2 0 R /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DetachedParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($detachedCMap) . " >>\nstream\n{$detachedCMap}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($detachedForm) . " >>\nstream\n{$detachedForm}\nendstream\nendobj\n"
        . "99 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 40 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /DetachedForm 7 0 R >> >>\nendobj\n"
        . "%%EOF";
};

$extractor = new PdfTextExtractor();
$propertyExtractor = new PdfPagePropertyExtractor();
$pdf = $pdfWithDetachedParent();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pageBoundary = $propertyExtractor->extractPageBoundaryMetadata($pdf);

if ($lines !== ['A'] || str_contains($plainText, 'Detached parent')) {
    throw new RuntimeException('Expected detached parent resources to stay out of WordPress text.');
}

echo '<!-- markerpdf-page-resource-catalog-parent-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-catalog-parent-boundary-currentbase',
    'native_boundary' => 'catalog /Pages /Kids path bounds inherited /Resources before WordPress text extraction',
    'catalog_page_count_preserved' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'detached_parent_resources_excluded' => !str_contains($plainText, 'Detached parent font leak'),
    'detached_form_xobject_excluded' => !str_contains($plainText, 'Detached parent form leak'),
    'page_resource_review_empty' => $pageBoundary === [],
    'visible_text_preserved' => $lines === ['A'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
