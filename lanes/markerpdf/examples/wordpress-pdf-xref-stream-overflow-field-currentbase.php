<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current oversized xref field guard page) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 700 Td (Stale oversized xref field fallback leak) Tj T* (Overflow object stream leak) Tj ET';

$members = [
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>',
];
$headerPairs = [];
$memberIndexes = [];
$objectData = '';
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}

$header = implode(' ', $headerPairs);
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress oversized xref-field object stream.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$wideSafeField = static fn (int $value): string => "\0\0\0\0" . pack('N', $value);
$wideXrefRow = static fn (int $type, string $fieldTwo, int $fieldThree = 0): string => chr($type) . $fieldTwo . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, $memberIndexes[4])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['9:0']);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous oversized xref-field stream.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 4 6 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);

$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $wideXrefRow(1, $wideSafeField($offsets['1:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['2:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['3:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['4:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['5:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['6:0']))
    . $wideXrefRow(1, $wideSafeField($offsets['9:0']))
    . $wideXrefRow(1, str_repeat("\xff", 8));
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current oversized xref-field stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 6 9 1 12 1] /W [1 8 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$widthEntry = $review['malformed_xref_stream_width_entries'][0] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'overflowing xref-stream row fields are rejected before current or previous object-stream text import',
    'malformed_xref_stream_width_count' => $review['malformed_xref_stream_width_count'],
    'malformed_width_owner_policy' => $widthEntry['owner_policy'] ?? null,
    'malformed_width_indexes' => $widthEntry['malformed_width_indexes'] ?? [],
    'overflow_object_number' => $widthEntry['overflow_object_number'] ?? null,
    'overflow_row_index' => $widthEntry['overflow_row_index'] ?? null,
    'overflow_field_index' => $widthEntry['overflow_field_index'] ?? null,
    'overflow_field_width' => $widthEntry['overflow_field_width'] ?? null,
    'rejected_before_row_decode' => ($widthEntry['rejected_before_row_decode'] ?? false) === true,
    'visible_text_empty' => $plainText === '' && $extractor->extractTextLines($pdf) === [],
    'current_text_excluded' => !str_contains($plainText, 'Current oversized xref field guard page'),
    'stale_object_stream_text_excluded' => !str_contains($plainText, 'Overflow object stream leak'),
    'stale_direct_text_excluded' => !str_contains($plainText, 'Stale oversized xref field fallback leak'),
];

echo '<!-- markerpdf-xref-stream-overflow-field-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

if ($smoke['visible_text_empty'] !== true || $smoke['current_text_excluded'] !== true) {
    throw new RuntimeException('Overflowing xref-stream row imported current WordPress text.');
}
if ($smoke['stale_object_stream_text_excluded'] !== true || $smoke['stale_direct_text_excluded'] !== true) {
    throw new RuntimeException('Overflowing xref-stream row leaked stale WordPress fallback text.');
}
if ($smoke['malformed_xref_stream_width_count'] !== 1) {
    throw new RuntimeException('Expected one overflowing xref-stream field review entry.');
}
if ($smoke['malformed_width_owner_policy'] !== 'overflowing_xref_stream_field_value') {
    throw new RuntimeException('Expected overflowing xref-stream field owner policy.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must remain native PHP without models or external PDF tools.');
}
