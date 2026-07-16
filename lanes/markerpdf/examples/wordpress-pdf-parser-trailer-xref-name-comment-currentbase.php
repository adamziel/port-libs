<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale comment trailer page) Tj T* (Comment Root wins leak) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current escaped trailer page) Tj T* (Token trailer wins) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . "trailer\n<< /Size 15 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(10, 0, '<< /Type /Catalog /Pages 11 0 R >>');
$addObject(11, 0, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
$addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 14 0 R >> >> /Contents 13 0 R >>');
$addObject(13, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(14, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefRow(0, 65535, 'f')
    . "10 5\n"
    . $xrefRow($offsets['10:0'])
    . $xrefRow($offsets['11:0'])
    . $xrefRow($offsets['12:0'])
    . $xrefRow($offsets['13:0'])
    . $xrefRow($offsets['14:0'])
    . "% trailer << /Root 1 0 R /Prev {$previousXrefOffset} /CommentOnly /Stale#52oot >>\n"
    . "trailer\n<< /Size 15 /Ro#6ft 10 0 R /Pre#76 {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

if ($lines !== ['Current escaped trailer page', 'Token trailer wins']) {
    throw new RuntimeException('Expected token-aware trailer parsing to select the escaped current trailer root.');
}

echo '<!-- markerpdf-parser-trailer-xref-name-comment-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-xref-trailer-token-parser',
    'native_boundary' => 'PDF xref-table trailer keywords inside comments are ignored and escaped trailer names are decoded',
    'escaped_root_name_resolved' => str_contains($plainText, 'Current escaped trailer page'),
    'escaped_prev_name_resolved' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'comment_trailer_dictionary_ignored' => !str_contains($plainText, 'Comment Root wins leak'),
    'stale_root_page_excluded' => !str_contains($plainText, 'Stale comment trailer page'),
    'comment_name_text_excluded' => !str_contains($plainText, 'Stale#52oot'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
