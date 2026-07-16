<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$directLeak = 'BT /F1 12 Tf 72 720 Td (Malformed Size direct fallback leak) Tj ET';
$compressedLeak = 'BT /F1 12 Tf 72 700 Td (Malformed Size object-stream leak) Tj T* (Bad Size row range imported) Tj ET';

$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>';
$header = '7 0';
$objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress malformed xref-size object-stream smoke fixture.');
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

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 7 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($directLeak) . " >>\nstream\n{$directLeak}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($compressedLeak) . " >>\nstream\n{$compressedLeak}\nendstream");

$xrefRows = ''
    . $xrefRow(0, 0, 255)
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(0, 0, 0)
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress malformed xref-size smoke stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Root 1 0 R /Size /BadSize /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$sizeEntry = $review['malformed_xref_stream_size_entries'][0] ?? [];

$summary = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref streams without /Index must provide a strict integer /Size before object-stream rows can select WordPress import text',
    'malformed_size_rejected' => ($review['malformed_xref_stream_size_count'] ?? null) === 1,
    'owner_policy' => $sizeEntry['owner_policy'] ?? null,
    'size_value' => $sizeEntry['size_value'] ?? null,
    'resolved_size' => $sizeEntry['resolved_size'] ?? null,
    'index_array_absent' => ($sizeEntry['index_array_absent'] ?? null) === true,
    'rejected_before_row_decode' => ($sizeEntry['rejected_before_row_decode'] ?? null) === true,
    'visible_text_empty' => $plainText === '',
    'direct_fallback_excluded' => !str_contains($plainText, 'Malformed Size direct fallback leak'),
    'object_stream_fallback_excluded' => !str_contains($plainText, 'Malformed Size object-stream leak')
        && !str_contains($plainText, 'Bad Size row range imported'),
    'compressed_entries_expanded' => $review['compressed_entry_count'] ?? null,
];

foreach ([
    'malformed_size_rejected',
    'index_array_absent',
    'rejected_before_row_decode',
    'visible_text_empty',
    'direct_fallback_excluded',
    'object_stream_fallback_excluded',
] as $requiredFlag) {
    if (($summary[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Malformed xref-stream /Size smoke failed: ' . $requiredFlag);
    }
}

if (($summary['owner_policy'] ?? null) !== 'non_integer_xref_stream_size_without_index') {
    throw new RuntimeException('Malformed xref-stream /Size smoke failed: owner_policy');
}

if (($summary['compressed_entries_expanded'] ?? null) !== 0) {
    throw new RuntimeException('Malformed xref-stream /Size smoke expanded compressed entries.');
}

echo '<!-- markerpdf-xref-stream-malformed-size-objectstream-currentbase-smoke ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
