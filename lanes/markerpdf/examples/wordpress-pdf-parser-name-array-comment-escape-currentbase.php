<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current escaped xref array page) Tj T* (Comment-safe Index wins) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale unreferenced direct page) Tj T* (Escaped array parser leak) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$currentRows = ''
    . $xrefRow(1, $offsets['1:0'], 0)
    . $xrefRow(1, $offsets['2:0'], 0)
    . $xrefRow(1, $offsets['3:0'], 0)
    . $xrefRow(1, $offsets['4:0'], 0)
    . $xrefRow(1, $offsets['5:0'], 0);
$compressedXref = gzcompress($currentRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress escaped xref array fixture.');
}

$currentXrefOffset = $addObject(
    20,
    0,
    "<< /Type /XRef /Si#7ae 21 /Ro#6ft 1 0 R\n"
        . "/In#64ex [ % 10 3 is comment-only stale direct range\n 1 5 ]\n"
        . "/#57 [ 1 4 % 9 9 is comment-only malformed width\n 1 ]\n"
        . "/Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream"
);
$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-parser-name-array-comment-escape-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-stream-name-array-parser',
    'escaped_index_name_resolved' => $lines === ['Current escaped xref array page', 'Comment-safe Index wins'],
    'escaped_w_name_resolved' => $lines === ['Current escaped xref array page', 'Comment-safe Index wins'],
    'array_comment_numbers_ignored' => !str_contains($plainText, '10 3'),
    'stale_currentbase_direct_catalog_excluded' => !str_contains($plainText, 'Stale unreferenced direct page'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
