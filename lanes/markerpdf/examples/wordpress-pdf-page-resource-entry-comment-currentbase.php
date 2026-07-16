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
            throw new RuntimeException('Unable to encode WordPress page-resource entry comment CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /WordPressPageResourceEntryCommentCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET '
    . 'q /CommentForm Do Q '
    . '/Span /CommentActual BDC BT /F1 12 Tf 72 680 Td (Glyph actual leak) Tj ET EMC '
    . 'q /StaleForm Do Q';
$commentForm = 'BT /F1 12 Tf 12 24 Td (Comment entry inherited form text) Tj ET';
$staleForm = 'BT /F1 12 Tf 12 24 Td (Comment entry stale form leak) Tj ET';
$cmap = $toUnicodeCMap([
    '41' => 'Comment entry inherited font text',
]);

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /Resources 30 0 R >> >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /CommentEntryFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($commentForm) . " >>\nstream\n{$commentForm}\nendstream\nendobj\n"
    . "8 0 obj\n<< /ActualText (Comment entry inherited actual text) >>\nendobj\n"
    . "9 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($staleForm) . " >>\nstream\n{$staleForm}\nendstream\nendobj\n"
    . "10 0 obj\n<< "
    . "/Font << /F1 5 % font resource object/generation split by PDF comment\n 0 % font generation/R split by PDF comment\n R >> "
    . "/XObject << /CommentForm 7 % form resource object/generation split by PDF comment\n 0 % form generation/R split by PDF comment\n R >> "
    . "/Properties << /CommentActual 8 % property resource object/generation split by PDF comment\n 0 % property generation/R split by PDF comment\n R >> "
    . ">>\nendobj\n"
    . "30 0 obj\n<< /Font << /F1 5 0 R >> /XObject << /StaleForm 9 0 R >> /Properties << /CommentActual 31 0 R >> >>\nendobj\n"
    . "31 0 obj\n<< /ActualText (Comment entry stale ActualText leak) >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
$resources = $boundary[0]['resources'] ?? [];
$expected = [
    'Comment entry inherited font text',
    'Comment entry inherited form text',
    'Comment entry inherited actual text',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected comment-delimited inherited resource entries to drive WordPress text.');
}

if (($resources['resource_object'] ?? null) !== 10 || ($resources['properties_names'] ?? null) !== ['CommentActual']) {
    throw new RuntimeException('Expected inherited resource entry metadata to preserve comment-delimited references.');
}

echo '<!-- markerpdf-page-resource-entry-comment-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-page-resource-entry-comment-currentbase',
    'native_boundary' => 'PDF comments are whitespace inside inherited Font, XObject, and Properties resource entry references before WordPress paragraph rendering',
    'inherited_resource_object' => $resources['resource_object'] ?? null,
    'font_entry_comment_reference_resolved' => ($resources['font_names'] ?? null) === ['F1'],
    'xobject_entry_comment_reference_resolved' => ($resources['xobject_names'] ?? null) === ['CommentForm'],
    'property_entry_comment_reference_resolved' => ($resources['properties_names'] ?? null) === ['CommentActual'],
    'glyph_actual_text_excluded' => !str_contains($plainText, 'Glyph actual leak'),
    'stale_private_resource_excluded' => !str_contains($plainText, 'Comment entry stale form leak')
        && !str_contains($plainText, 'Comment entry stale ActualText leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
