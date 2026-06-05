<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current omitted carrier row page) Tj T* (Direct carrier inferred) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Omitted carrier stale fallback leak) Tj ET';

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
    throw new RuntimeException('Unable to compress omitted-carrier object-stream smoke fixture.');
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
$addObject(8, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$xrefRows = ''
    . $xrefRow(2, 6, $memberIndexes[1])
    . $xrefRow(2, 6, $memberIndexes[2])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, $memberIndexes[4])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(0, 0, 65535);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress omitted-carrier xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5 8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-object-stream-omitted-carrier-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 xref-stream type-2 rows infer a present direct ObjStm carrier when the carrier row is omitted',
    'direct_object_stream_present' => str_contains($pdf, '/Type /ObjStm'),
    'carrier_row_omitted_from_xref_stream' => true,
    'current_compressed_page_selected' => str_contains($plainText, 'Current omitted carrier row page'),
    'direct_carrier_inferred' => str_contains($plainText, 'Direct carrier inferred'),
    'stale_free_stream_excluded' => !str_contains($plainText, 'Omitted carrier stale fallback leak'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'object_4_owner_policy' => $entries[4]['object_stream_owner_policy'] ?? null,
    'object_4_selection_policy' => $entries[4]['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
