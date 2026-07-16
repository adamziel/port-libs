<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale Prev object-stream page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current free Prev review page) Tj T* (Compressed Prev member suppressed) Tj ET';

$members = [
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];
$objectData = '';
$headerPairs = [];
$memberIndexes = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerPairs);
$compressedObjectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress previous object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

$previousRows = ''
    . $xrefStreamRow(1, $offsets['1:0'], 0)
    . $xrefStreamRow(1, $offsets['2:0'], 0)
    . $xrefStreamRow(1, $offsets['3:0'], 0)
    . $xrefStreamRow(2, 6, $memberIndexes[4])
    . $xrefStreamRow(1, $offsets['5:0'], 0)
    . $xrefStreamRow(1, $offsets['6:0'], 0);
$previousCompressedXref = gzcompress($previousRows);
if (!is_string($previousCompressedXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $offsets['1:1'], 1)
    . $xrefStreamRow(1, $offsets['2:1'], 1)
    . $xrefStreamRow(0, 4, 1)
    . $xrefStreamRow(1, $offsets['8:0'], 0)
    . $xrefStreamRow(1, $offsets['9:0'], 0);
$currentCompressedXref = gzcompress($currentRows);
if (!is_string($currentCompressedXref)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
    . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);

echo '<!-- markerpdf-xref-object-stream-free-entry-prev-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest xref-stream free row suppresses stale Prev object-stream member before Gutenberg paragraph rendering',
    'uses_current_free_prev_page' => str_contains($plainText, 'Current free Prev review page'),
    'suppresses_stale_prev_object_stream_member' => !str_contains($plainText, 'Stale Prev object-stream page'),
    'keeps_current_free_row_authoritative' => str_contains($plainText, 'Compressed Prev member suppressed'),
    'page_count' => $outline['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
