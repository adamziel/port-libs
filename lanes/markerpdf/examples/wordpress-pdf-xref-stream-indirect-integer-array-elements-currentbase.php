<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Indirect xref integer array page) Tj T* (Object stream rows selected) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale direct xref integer page) Tj T* (Indirect integer array fallback leak) Tj ET';

$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
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
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress indirect xref integer object-stream smoke fixture.');
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

$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(30, 0, '1');
$addObject(31, 0, '4');
$addObject(32, 0, '1');
$addObject(40, 0, '1');
$addObject(41, 0, '6');

$xrefRows = ''
    . $xrefRow(2, 6, $memberIndexes[1])
    . $xrefRow(2, 6, $memberIndexes[2])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, $memberIndexes[4])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress indirect xref integer xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 42 /Root 1 0 R /Index [40 0 R 41 0 R] /W [30 0 R 31 0 R 32 0 R] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 8 0 R >>');
$addObject(8, 0, '<< /Type /Pages /Kids [9 0 R] /Count 1 >>');
$addObject(9, 0, '<< /Type /Page /Parent 8 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>');
$addObject(10, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-stream-indirect-integer-array-elements-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream /W and /Index array integer elements resolve indirect scalar helpers before object-stream row selection',
    'indirect_integer_w_elements_resolved' => ($review['malformed_xref_stream_width_count'] ?? null) === 0,
    'indirect_integer_index_elements_resolved' => ($review['malformed_xref_stream_index_count'] ?? null) === 0,
    'object_stream_rows_selected' => ($review['compressed_entry_count'] ?? null) === 3,
    'current_object_stream_text_visible' => str_contains($plainText, 'Indirect xref integer array page')
        && str_contains($plainText, 'Object stream rows selected'),
    'stale_direct_text_excluded' => !str_contains($plainText, 'Stale direct xref integer page')
        && !str_contains($plainText, 'Indirect integer array fallback leak'),
    'object_4_selection_policy' => $entries[4]['selection_policy'] ?? null,
    'object_4_carrier_policy' => $entries[4]['object_stream_owner_policy'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
