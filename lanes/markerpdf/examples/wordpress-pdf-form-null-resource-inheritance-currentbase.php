<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (string $text): string {
    $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', $text);
    if ($encoded === false) {
        throw new RuntimeException('Unable to encode Form null-resource CMap text.');
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
        . "CMapName currentdict /WordPressFormNullResourcesCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageContent = 'q /DirectNullForm Do Q q /IndirectNullForm Do Q q /ExplicitEmptyForm Do Q';
$directNullForm = 'q /InheritedNestedForm Do Q';
$indirectNullForm = 'q /InheritedNestedForm Do Q';
$explicitEmptyForm = 'q /InheritedNestedForm Do Q';
$nestedForm = 'BT /F1 12 Tf 12 24 Td <41> Tj ET';
$cmap = $toUnicodeCMap('Null form inherited nested text');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources null /Length " . strlen($directNullForm) . " >>\nstream\n{$directNullForm}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 12 0 R /Length " . strlen($indirectNullForm) . " >>\nstream\n{$indirectNullForm}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 13 0 R /Length " . strlen($explicitEmptyForm) . " >>\nstream\n{$explicitEmptyForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($nestedForm) . " >>\nstream\n{$nestedForm}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /NullFormInherited /Encoding /Identity-H /ToUnicode 11 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 9 0 R >> /XObject << /DirectNullForm 5 0 R /IndirectNullForm 6 0 R /ExplicitEmptyForm 7 0 R /InheritedNestedForm 8 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "12 0 obj\nnull\nendobj\n"
    . "13 0 obj\n<< >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Null form inherited nested text',
    'Null form inherited nested text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected direct and indirect null Form resources to inherit invoking page resources.');
}

$flags = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Form XObject direct and indirect null /Resources inherit the invoking page resources before Gutenberg paragraph rendering',
    'direct_null_form_resources_inherit_page' => $lines[0] === 'Null form inherited nested text',
    'indirect_null_form_resources_inherit_page' => $lines[1] === 'Null form inherited nested text',
    'explicit_empty_form_resources_block_page_xobject' => substr_count($plainText, 'Null form inherited nested text') === 2,
    'visible_text_excludes_resource_names' => !str_contains($plainText, 'InheritedNestedForm')
        && !str_contains($plainText, 'ExplicitEmptyForm'),
];

echo '<!-- markerpdf-form-null-resource-inheritance ' . htmlspecialchars(json_encode(
    $flags,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
