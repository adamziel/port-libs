<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$guardContent = 'BT /F1 12 Tf 72 720 Td (Current object-stream member-tail guard page) Tj ET';
$malformedContent = 'BT /F1 12 Tf 72 700 Td (Malformed member-tail page leak) Tj T* (Trailing object-stream operand accepted) Tj ET';

$malformedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >> endobj (tail operand leak)';
$reviewMember = '<< /Type /Review /Note (valid later member after malformed tail) >>';
$header = '4 0 12 ' . strlen($malformedPage . "\n");
$objectStreamPlain = $header . "\n" . $malformedPage . "\n" . $reviewMember . "\n";
$objectStream = gzcompress($objectStreamPlain);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress object-stream member-tail smoke.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($malformedContent) . " >>\nstream\n{$malformedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(2, 6, 1);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress object-stream member-tail xref smoke.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$malformedEntry = $entries[4] ?? [];
$laterEntry = $entries[12] ?? [];

$smoke = [
    'uses_guard_page_text' => $text === 'Current object-stream member-tail guard page',
    'malformed_member_text_suppressed' => !str_contains($text, 'Malformed member-tail page leak')
        && !str_contains($text, 'Trailing object-stream operand accepted')
        && !str_contains($text, 'tail operand leak'),
    'later_review_member_not_visible' => !str_contains($text, 'valid later member after malformed tail'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'malformed_object_stream_member_tail_count' => $review['malformed_object_stream_member_tail_count'] ?? null,
    'malformed_member_tail_rejected' => $malformedEntry['malformed_member_tail_rejected'] ?? null,
    'malformed_member_has_single_value' => $malformedEntry['object_stream_member_has_single_value'] ?? null,
    'later_member_has_single_value' => $laterEntry['object_stream_member_has_single_value'] ?? null,
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

if ($smoke['uses_guard_page_text'] !== true) {
    throw new RuntimeException('Expected guard page text to be the only imported WordPress text.');
}
if ($smoke['malformed_member_text_suppressed'] !== true || $smoke['later_review_member_not_visible'] !== true) {
    throw new RuntimeException('Malformed object-stream member tail leaked into WordPress text.');
}
if ($smoke['malformed_object_stream_member_tail_count'] !== 1 || $smoke['malformed_member_tail_rejected'] !== true) {
    throw new RuntimeException('Expected one review-visible malformed object-stream member-tail rejection.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must remain native PHP without models or external PDF tools.');
}
