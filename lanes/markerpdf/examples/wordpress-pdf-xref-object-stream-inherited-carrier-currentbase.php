<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
    $compressed = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress inherited-carrier object stream smoke fixture.');
    }

    return [$header, $compressed];
};

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited carrier page) Tj T* (Previous carrier row reused) Tj ET';
$staleFallbackContent = 'BT /F1 12 Tf 72 720 Td (Stale direct fallback leak) Tj ET';

$memberIndexes = [];
[$header, $compressedObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (compressed page selected through inherited carrier row) >>',
], $memberIndexes);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleFallbackContent) . " >>\nstream\n{$staleFallbackContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');

$previousRows = ''
    . $row(1, $offsets['1:0'], 0)
    . $row(1, $offsets['2:0'], 0)
    . $row(1, $offsets['3:0'], 0)
    . $row(1, $offsets['5:0'], 0)
    . $row(1, $offsets['6:0'], 0)
    . $row(1, $offsets['8:0'], 0);
$previousCompressedXref = gzcompress($previousRows);
if (!is_string($previousCompressedXref)) {
    throw new RuntimeException('Unable to compress inherited-carrier previous xref stream smoke fixture.');
}
$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 2 8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $row(1, $offsets['1:1'], 1)
    . $row(1, $offsets['2:1'], 1)
    . $row(2, 6, $memberIndexes[4])
    . $row(1, $offsets['9:0'], 0);
$currentCompressedXref = gzcompress($currentRows);
if (!is_string($currentCompressedXref)) {
    throw new RuntimeException('Unable to compress inherited-carrier current xref stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
    . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$outline = $extractor->extractOutlineMetadata($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

echo '<!-- markerpdf-xref-object-stream-inherited-carrier-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest xref-stream type-2 member rows can reuse an inherited direct object-stream carrier row from /Prev while exposing that provenance for review',
    'uses_current_inherited_carrier_page' => str_contains($plainText, 'Current inherited carrier page'),
    'reuses_previous_carrier_row' => str_contains($plainText, 'Previous carrier row reused'),
    'excludes_stale_direct_fallback' => !str_contains($plainText, 'Stale direct fallback leak'),
    'excludes_compressed_member_metadata' => !str_contains($plainText, 'compressed page selected through inherited carrier row'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'object_stream' => $entry['object_stream'] ?? null,
    'object_stream_entry_inherited_from_prev' => $entry['object_stream_entry_inherited_from_prev'] ?? null,
    'object_stream_inherited_xref_offset' => $entry['object_stream_inherited_xref_offset'] ?? null,
    'page_count' => $outline['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
