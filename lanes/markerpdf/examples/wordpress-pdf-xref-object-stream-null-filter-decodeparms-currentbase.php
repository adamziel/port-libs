<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$directContent = 'BT /F1 12 Tf 72 720 Td (Direct WordPress import guard page) Tj ET';
$compressedContent = 'BT /F1 12 Tf 72 700 Td (WordPress object-stream page from null filter slot) Tj T* (Unresolved null DecodeParms ignored) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';

$header = '4 0';
$objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress WordPress null-filter DecodeParms object stream.');
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
$addObject(5, 0, "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1)
    . ' /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R null ] /Length ' . strlen($objectStream)
    . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress WordPress null-filter DecodeParms xref stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$summary = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF object streams ignore DecodeParms entries aligned to null filter slots before WordPress paragraph extraction',
    'direct_guard_visible' => str_contains($plainText, 'Direct WordPress import guard page'),
    'object_stream_page_visible' => str_contains($plainText, 'WordPress object-stream page from null filter slot'),
    'null_decodeparms_slot_ignored' => str_contains($plainText, 'Unresolved null DecodeParms ignored')
        && !str_contains($plainText, '99 0 R'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'strict_dependency_rejection_count' => $review['strict_dependency_rejection_count'],
    'object_stream_member_count' => $entry['object_stream_member_count'] ?? null,
    'object_stream_carrier_has_filter' => $entry['object_stream_carrier_has_filter'] ?? false,
    'selection_policy' => $entry['selection_policy'] ?? null,
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'direct_guard_visible',
        'object_stream_page_visible',
        'null_decodeparms_slot_ignored',
        'object_stream_carrier_has_filter',
    ] as $flag) {
        if (($summary[$flag] ?? false) !== true) {
            throw new RuntimeException('Expected null-filter object-stream smoke flag to pass: ' . $flag);
        }
    }
    if ($summary['compressed_entry_count'] !== 1 || $summary['strict_dependency_rejection_count'] !== 0) {
        throw new RuntimeException('Expected object-stream xref review to expand one compressed page without strict dependency rejection.');
    }
}

echo '<!-- markerpdf-pdf-xref-object-stream-null-filter-decodeparms-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
