<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode CMap fixture text.');
    }

    return "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<41> <" . strtoupper(bin2hex($encoded)) . ">\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /FormNullWrapperCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'q /WrappedNullForm Do Q q /WrappedEmptyForm Do Q';
$wrappedNullForm = 'q /InheritedNestedForm Do Q';
$wrappedEmptyForm = 'q /InheritedNestedForm Do Q';
$nestedForm = 'BT /F1 12 Tf 12 24 Td <41> Tj ET';
$cmap = $toUnicodeCMap('Wrapped null form inherited nested text');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 12 0 R /Length " . strlen($wrappedNullForm) . " >>\nstream\n{$wrappedNullForm}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 14 0 R /Length " . strlen($wrappedEmptyForm) . " >>\nstream\n{$wrappedEmptyForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($nestedForm) . " >>\nstream\n{$nestedForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WrappedNullFormInherited /Encoding /Identity-H /ToUnicode 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 8 0 R >> /XObject << /WrappedNullForm 5 0 R /WrappedEmptyForm 6 0 R /InheritedNestedForm 7 0 R >> >>\nendobj\n"
    . "12 0 obj\n13 0 R\nendobj\n"
    . "13 0 obj\nnull\nendobj\n"
    . "14 0 obj\n15 0 R\nendobj\n"
    . "15 0 obj\n<< >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$flags = [
    'scenario' => 'wordpress-pdf-page-resource-form-null-wrapper-currentbase',
    'native_boundary' => 'Form XObject /Resources indirect wrappers resolving to null inherit invoking page resources before Gutenberg paragraph rendering',
    'wrapped_null_form_inherits_page_resources' => $lines === ['Wrapped null form inherited nested text'],
    'wrapped_empty_form_resources_stay_explicit' => substr_count($plainText, 'Wrapped null form inherited nested text') === 1,
    'raw_resource_names_exposed' => str_contains($plainText, 'InheritedNestedForm')
        || str_contains($plainText, 'WrappedEmptyForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $flags['wrapped_null_form_inherits_page_resources'] !== true
    || $flags['wrapped_empty_form_resources_stay_explicit'] !== true
    || $flags['raw_resource_names_exposed'] !== false
) {
    throw new RuntimeException('Expected wrapped-null Form XObject resources to inherit page resources exactly once.');
}

echo '<!-- markerpdf-page-resource-form-null-wrapper-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
