<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$toUnicodeCMap = static function (array $entries, string $name): string {
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
            throw new RuntimeException('Unable to encode form comment-reference smoke CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /{$name} defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageContent = 'BT /Fpage 12 Tf 72 720 Td <41> Tj ET q /Comment#20Form Do Q q /Null#20Wrapped#20Form Do Q';
$localForm = 'BT /Flocal 10 Tf 12 24 Td <42> Tj ET';
$nullWrappedForm = 'BT /Fpage 10 Tf 12 24 Td <43> Tj ET q /Inherited#20Nested Do Q';
$nestedForm = 'BT /Fpage 9 Tf 6 12 Td <44> Tj ET';
$pageCMap = $toUnicodeCMap([
    '41' => 'Page inherited form-comment font text',
    '43' => 'Null-wrapper form inherited page font text',
    '44' => 'Null-wrapper form inherited nested text',
], 'WordPressPageResourceFormCommentReferencePageCMap');
$localCMap = $toUnicodeCMap([
    '42' => 'Comment-delimited form local resource text',
], 'WordPressPageResourceFormCommentReferenceLocalCMap');

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /PageCommentFormFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageCMap) . " >>\nstream\n{$pageCMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /LocalCommentFormFont /Encoding /Identity-H /ToUnicode 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($localCMap) . " >>\nstream\n{$localCMap}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Font << /Fpage 5 0 R >> /XObject << /Comment#20Form 20 0 R /Null#20Wrapped#20Form 21 0 R /Inherited#20Nested 22 0 R >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Resources 30 % form resource object/generation split by PDF comment\n 0 % form generation/R split by PDF comment\n R /Length " . strlen($localForm) . " >>\nstream\n{$localForm}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 240 80] /Resources 31 0 R /Length " . strlen($nullWrappedForm) . " >>\nstream\n{$nullWrappedForm}\nendstream\nendobj\n"
    . "22 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 60] /Length " . strlen($nestedForm) . " >>\nstream\n{$nestedForm}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Font << /Flocal 7 0 R >> >>\nendobj\n"
    . "31 0 obj\n32 % null-wrapper object/generation split by PDF comment\n 0 % null-wrapper generation/R split by PDF comment\n R\nendobj\n"
    . "32 0 obj\nnull\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Page inherited form-comment font text',
    'Comment-delimited form local resource text',
    'Null-wrapper form inherited page font text',
    'Null-wrapper form inherited nested text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected comment-delimited Form XObject resources to render native WordPress paragraphs.');
}

if (($resources['xobject_names'] ?? null) !== ['Comment Form', 'Null Wrapped Form', 'Inherited Nested']) {
    throw new RuntimeException('Expected inherited page XObject names in resource metadata.');
}

echo '<!-- markerpdf-page-resource-form-comment-reference-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-form-comment-reference-currentbase',
    'native_boundary' => 'Form XObject /Resources references treat PDF comments as whitespace and null wrappers inherit invoking page resources',
    'form_comment_resource_reference_resolved' => in_array('Comment-delimited form local resource text', $lines, true),
    'form_null_wrapper_inherits_page_resources' => in_array('Null-wrapper form inherited page font text', $lines, true),
    'nested_form_inherits_invoking_page_resources' => in_array('Null-wrapper form inherited nested text', $lines, true),
    'page_resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'page_resource_object' => $resources['resource_object'] ?? null,
    'page_xobject_names' => $resources['xobject_names'] ?? [],
    'resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'Comment Form')
        && !str_contains($plainText, 'Null Wrapped Form')
        && !str_contains($plainText, 'Flocal'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
