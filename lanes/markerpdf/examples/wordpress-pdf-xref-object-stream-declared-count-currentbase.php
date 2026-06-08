<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current declared-count page) Tj T* (Ignored header overrun) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 700 Td (Overrun object-stream page leak) Tj ET';

$decoyMember = '<< /Type /Review /Note (declared-count first member decoy) >>';
$stalePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>';
$currentPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$extraOffset = strlen($decoyMember . "\n");
$currentOffset = strlen($decoyMember . "\n" . $stalePage . "\n");
$header = '12 0 4 ' . $currentOffset . ' 8 ' . $extraOffset;
$objectData = $decoyMember . "\n" . $stalePage . "\n" . $currentPage . "\n";
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress declared-count object-stream smoke fixture.');
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
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 1)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(0, 0)
    . $xrefRow(2, 6, 2)
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress declared-count xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 9] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$overrunEntry = $entries[8] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF object-stream /N controls imported member count before WordPress text extraction',
    'uses_declared_current_page' => $lines === ['Current declared-count page', 'Ignored header overrun'],
    'excluded_overrun_page' => !str_contains($plainText, 'Overrun object-stream page leak'),
    'excluded_decoy_member' => !str_contains($plainText, 'declared-count first member decoy'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'out_of_range_member_index_rejection_count' => $review['out_of_range_member_index_rejection_count'] ?? null,
    'overrun_member_policy' => $overrunEntry['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

echo '<!-- markerpdf-xref-object-stream-declared-count-currentbase-smoke ' . htmlspecialchars(json_encode($smoke, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

if ($smoke['uses_declared_current_page'] !== true) {
    throw new RuntimeException('Expected declared /N members to keep the current object-stream page importable.');
}
if ($smoke['excluded_overrun_page'] !== true || $smoke['excluded_decoy_member'] !== true) {
    throw new RuntimeException('Object-stream header overrun leaked into WordPress text output.');
}
if (
    $smoke['out_of_range_member_index_rejection_count'] !== 1
    || $smoke['overrun_member_policy'] !== 'out_of_range_object_stream_member_index'
) {
    throw new RuntimeException('Expected overrun xref type-2 member index to remain review-visible and rejected.');
}
