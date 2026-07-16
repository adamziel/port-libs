<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf 72 720 Td (Prev indirect compressed object stream page) Tj T* (Object stream prev helper selected) Tj ET';

$objectStream = static function (array $members, array &$memberIndexes): array {
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $plain = $header . "\n" . $objectData;
    $compressed = gzcompress($plain);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress indirect Prev object-stream smoke fixture.');
    }

    return [$header, $compressed];
};

$prevMemberIndexes = [];
[$prevHeader, $prevCompressedObjectStream] = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
], $prevMemberIndexes);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($prevMemberIndexes) . ' /First ' . (strlen($prevHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($prevCompressedObjectStream) . " >>\nstream\n{$prevCompressedObjectStream}\nendstream");
$addObject(10, 0, '<< /Type /Font /Subtype /Type3 /CharProcs << /A 5 0 R >> /Encoding << /Type /Encoding /Differences [65 /A] >> /FirstChar 65 /LastChar 65 /Widths [500] >>');

$prevXrefRows = ''
    . $xrefRow(2, 6, $prevMemberIndexes[1])
    . $xrefRow(2, 6, $prevMemberIndexes[2])
    . $xrefRow(2, 6, $prevMemberIndexes[3])
    . $xrefRow(2, 6, $prevMemberIndexes[4])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['10:0']);
$prevCompressedXref = gzcompress($prevXrefRows);
if (!is_string($prevCompressedXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}

$prevXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 10 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($prevCompressedXref) . " >>\n"
    . "stream\n{$prevCompressedXref}\nendstream\nendobj\n";

$helperMemberIndexes = [];
[$helperHeader, $helperCompressedObjectStream] = $objectStream([
    30 => (string) $prevXrefOffset,
], $helperMemberIndexes);
$addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($helperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($helperCompressedObjectStream) . " >>\nstream\n{$helperCompressedObjectStream}\nendstream");

$currentXrefRows = ''
    . $xrefRow(1, $offsets['7:0'])
    . $xrefRow(2, 7, $helperMemberIndexes[30]);
$currentCompressedXref = gzcompress($currentXrefRows);
if (!is_string($currentCompressedXref)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /Index [7 1 30 1] /W [1 4 1] /Prev 30 0 R /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
    . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

echo '<!-- markerpdf-xref-stream-indirect-prev-object-stream-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current xref-stream indirect /Prev offset resolved from a compressed helper object before previous object-stream page trees are imported',
    'uses_prev_object_stream_page' => $lines === [
        'Prev indirect compressed object stream page',
        'Object stream prev helper selected',
    ],
    'recovers_prev_xref_offset_from_compressed_helper' => ($entries[30]['object_stream'] ?? null) === 7,
    'previous_catalog_recovered_from_object_stream' => ($entries[1]['object_stream'] ?? null) === 6,
    'excludes_charproc_fallback_scan' => !str_contains($plainText, 'CharProcs'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
