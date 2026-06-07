<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$leakingContent = 'BT /F1 12 Tf 72 720 Td (Malformed row-alignment object-stream page leak) Tj T* (Trailing xref byte ignored) Tj ET';

$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
];
$memberIndexes = [];
$headerPairs = [];
$objectData = '';
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}
$header = implode(' ', $headerPairs);
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress row-alignment object stream.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($leakingContent) . " >>\nstream\n{$leakingContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = ''
    . $xrefRow(2, 6, $memberIndexes[1])
    . $xrefRow(2, 6, $memberIndexes[2])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, $memberIndexes[4])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . 'X';
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress unaligned xref stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$alignmentEntry = $review['malformed_xref_stream_row_alignment_entries'][0] ?? [];

$smoke = [
    'visible_text_empty' => $text === '',
    'compressed_member_suppressed' => !str_contains($text, 'Malformed row-alignment object-stream page leak')
        && !str_contains($text, 'Trailing xref byte ignored'),
    'malformed_xref_stream_row_alignment_count' => $review['malformed_xref_stream_row_alignment_count'] ?? 0,
    'row_alignment_owner_policy' => $alignmentEntry['owner_policy'] ?? null,
    'decoded_length' => $alignmentEntry['decoded_length'] ?? null,
    'entry_width' => $alignmentEntry['entry_width'] ?? null,
    'trailing_byte_count' => $alignmentEntry['trailing_byte_count'] ?? null,
    'executes_python_or_models' => $review['executes_python_or_models'],
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'],
];

foreach ($smoke as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    }

    echo $key . '=' . $value . PHP_EOL;
}

if ($smoke['visible_text_empty'] !== true || $smoke['compressed_member_suppressed'] !== true) {
    throw new RuntimeException('Malformed xref-stream rows leaked compressed WordPress text.');
}
if ($smoke['malformed_xref_stream_row_alignment_count'] !== 1) {
    throw new RuntimeException('Expected one malformed row-alignment review entry.');
}
if ($smoke['row_alignment_owner_policy'] !== 'unaligned_xref_stream_row_width') {
    throw new RuntimeException('Expected the unaligned xref-stream row-width owner policy.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must remain native PHP without models or external PDF tools.');
}
