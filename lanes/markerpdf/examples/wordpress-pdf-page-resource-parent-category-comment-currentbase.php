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
            throw new RuntimeException('Unable to encode page-resource parent/category comment CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceParentCategoryCommentCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /Fcat 12 Tf 72 720 Td <41> Tj T* /Span /CommentActual BDC <42> Tj EMC ET q /CommentForm Do Q';
$form = 'BT /Fcat 12 Tf 12 24 Td <43> Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Comment parent inherited font text',
    '42' => 'Comment parent raw ActualText glyph',
    '43' => 'Comment parent inherited form text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 % parent object/generation split by PDF comment\n 0 % parent generation/R split by PDF comment\n R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentParentFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($form) . " >>\nstream\n{$form}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText 9 0 R >>\nendobj\n"
    . "9 0 obj\n(Comment parent inherited actual text)\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font 20 % font category object/generation split by PDF comment\n 0 % font category generation/R split by PDF comment\n R "
    . "/XObject 21 % xobject category object/generation split by PDF comment\n 0 % xobject category generation/R split by PDF comment\n R "
    . "/Properties 22 % properties category object/generation split by PDF comment\n 0 % properties category generation/R split by PDF comment\n R "
    . ">>\nendobj\n"
    . "20 0 obj\n<< /Fcat 5 0 R >>\nendobj\n"
    . "21 0 obj\n<< /CommentForm 7 0 R >>\nendobj\n"
    . "22 0 obj\n<< /CommentActual 8 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Comment parent inherited font text',
    'Comment parent inherited actual text',
    'Comment parent inherited form text',
];

$flags = [
    'source' => 'native-pdf-page-resource-parent-category-comment-currentbase',
    'native_boundary' => 'PDF comments are whitespace in page /Parent references and inherited Font/XObject/Properties category references',
    'parent_comment_reference_resolved' => $lines === $expected,
    'resource_owner_object' => $resources['resource_owner_object'] ?? null,
    'resource_object' => $resources['resource_object'] ?? null,
    'resource_inherited' => $resources['inherited'] ?? null,
    'categories' => $resources['categories'] ?? [],
    'font_names' => $resources['font_names'] ?? [],
    'xobject_names' => $resources['xobject_names'] ?? [],
    'properties_names' => $resources['properties_names'] ?? [],
    'actual_text_replaces_raw_glyph' => !str_contains($plainText, 'Comment parent raw ActualText glyph'),
    'visible_text_excludes_resource_names' => !str_contains($plainText, 'CommentForm')
        && !str_contains($plainText, 'WordPressPageResourceParentCategoryCommentCMap'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $flags['parent_comment_reference_resolved'] !== true
    || $flags['resource_owner_object'] !== 2
    || $flags['resource_object'] !== 10
    || $flags['resource_inherited'] !== true
    || $flags['categories'] !== ['Font', 'XObject', 'Properties']
    || $flags['visible_text_excludes_resource_names'] !== true
    || $flags['actual_text_replaces_raw_glyph'] !== true
) {
    throw new RuntimeException('Expected comment-split inherited page resources to render as WordPress paragraphs.');
}

echo '<!-- markerpdf-page-resource-parent-category-comment-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
