<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$guardContent = 'BT /F1 12 Tf 72 720 Td (Current out-of-range index guard page) Tj ET';
$currentCompressedContent = 'BT /F1 12 Tf 72 700 Td (Out-of-range compressed member leak) Tj T* (Explicit bad index ignored) Tj ET';
$staleDirectContent = 'BT /F1 12 Tf 72 680 Td (Stale direct fallback leak) Tj ET';

$decoyMember = '<< /Type /Page /Parent 2 0 R /Note (decoy first object stream member) >>';
$currentMember = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$header = '12 0 4 ' . strlen($decoyMember . "\n");
$objectStreamBytes = $header . "\n" . $decoyMember . "\n" . $currentMember . "\n";
$objectStream = gzcompress($objectStreamBytes);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress out-of-range object stream.');
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
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentCompressedContent) . " >>\nstream\n{$currentCompressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($staleDirectContent) . " >>\nstream\n{$staleDirectContent}\nendstream");
$addObject(10, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 9)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(1, $offsets['10:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress out-of-range xref stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$text = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'uses_guard_page_text' => $text === 'Current out-of-range index guard page',
    'compressed_member_suppressed' => !str_contains($text, 'Out-of-range compressed member leak')
        && !str_contains($text, 'Explicit bad index ignored'),
    'stale_direct_page_suppressed' => !str_contains($text, 'Stale direct fallback leak'),
    'out_of_range_member_index_rejection_count' => $review['out_of_range_member_index_rejection_count'] ?? 0,
    'xref_member_index' => $entry['xref_member_index'] ?? null,
    'object_stream_member_count' => $entry['object_stream_member_count'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
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
    throw new RuntimeException('Expected the guard page to be the only imported WordPress text.');
}
if ($smoke['compressed_member_suppressed'] !== true || $smoke['stale_direct_page_suppressed'] !== true) {
    throw new RuntimeException('Out-of-range type-2 member index leaked stale or compressed text.');
}
if ($smoke['out_of_range_member_index_rejection_count'] !== 1) {
    throw new RuntimeException('Expected one review-visible out-of-range member-index rejection.');
}
if ($smoke['selection_policy'] !== 'out_of_range_object_stream_member_index') {
    throw new RuntimeException('Expected an out-of-range object-stream member-index selection policy.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must remain native PHP without models or external PDF tools.');
}
