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
            throw new RuntimeException('Unable to encode WordPress comment-null resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceCommentNullCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* /Span /NullActual BDC <42> Tj EMC ET q /ParentForm Do Q';
$form = 'BT /F1 12 Tf 12 24 Td <43> Tj ET';
$cMap = $toUnicodeCMap([
    '41' => 'Comment null inherited font text',
    '42' => 'Comment null physical glyph leak',
    '43' => 'Comment null inherited form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 11 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /WPCommentNullResource /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cMap) . " >>\nstream\n{$cMap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 260 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Comment null inherited actual text) >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /ParentForm 7 0 R >> /Properties << /NullActual 8 0 R >> >>\nendobj\n"
    . "11 0 obj\n% page-local resource null wrapper emitted by an incremental producer\nnull\n% trailing resource-null comment\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expectedLines = [
    'Comment null inherited font text',
    'Comment null inherited actual text',
    'Comment null inherited form text',
];

if ($lines !== $expectedLines) {
    throw new RuntimeException('Expected comment-wrapped null page Resources to inherit parent resources.');
}

if (($resources['resource_owner_object'] ?? null) !== 2 || ($resources['inherited'] ?? null) !== true) {
    throw new RuntimeException('Expected page-boundary metadata to report inherited parent resource owner.');
}

echo '<!-- markerpdf-page-resource-comment-null-inheritance-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-comment-null-inheritance-currentbase',
    'native_boundary' => 'comment-wrapped null /Resources objects inherit ancestor page-tree resources for WordPress import',
    'comment_wrapped_null_resources_inherit' => ($resources['resource_owner_object'] ?? null) === 2,
    'resource_lookup_objects' => $resources['resource_lookup_objects'] ?? [],
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'properties_names' => $resources['properties_names'] ?? [],
    'physical_glyph_text_excluded' => !str_contains($plainText, 'Comment null physical glyph leak'),
    'resource_names_excluded_from_paragraphs' => !str_contains($plainText, 'ParentForm'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
