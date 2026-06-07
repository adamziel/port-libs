<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleCompressedContent = 'BT /F1 12 Tf 72 700 Td (Stale duplicate xref-stream page) Tj T* (Earlier type two row leaked) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-row guard page) Tj ET';
$members = [
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    10 => '<< /Title (Stale duplicate metadata title) /Author (Earlier compressed Info row) >>',
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
$objectStreamPayload = $header . "\n" . $objectData;
$compressedObjectStream = gzcompress($objectStreamPayload);
if (!is_string($compressedObjectStream)) {
    throw new RuntimeException('Unable to compress duplicate-row object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");
$addObject(6, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
$addObject(8, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets[1])
    . $xrefRow(1, $offsets[2])
    . $xrefRow(1, $offsets[3])
    . $xrefRow(2, 6, 0)
    . $xrefRow(0, 0, 0)
    . $xrefRow(1, $offsets[5])
    . $xrefRow(1, $offsets[6])
    . $xrefRow(1, $offsets[8])
    . $xrefRow(1, $offsets[9])
    . $xrefRow(2, 6, $memberIndexes[10])
    . $xrefRow(0, 0, 0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress duplicate-row xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Info 10 0 R /Index [1 3 4 1 4 1 5 2 8 2 10 1 10 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (
    $lines !== ['Current duplicate-row guard page']
    || $review['compressed_entry_count'] !== 0
    || ($metadata['title'] ?? null) !== null
    || ($metadata['author'] ?? null) !== null
    || str_contains($plainText, 'Stale duplicate xref-stream page')
    || str_contains($plainText, 'Earlier type two row leaked')
    || str_contains($encodedMetadata, 'Stale duplicate metadata title')
    || str_contains($encodedMetadata, 'Earlier compressed Info row')
) {
    throw new RuntimeException('Expected later duplicate xref-stream free row to suppress stale object-stream page text.');
}

echo '<!-- markerpdf:pdf-xref-stream-duplicate-object-row ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-stream-duplicate-object-row',
    'paragraphs' => $lines,
    'compressed_entry_count' => $review['compressed_entry_count'],
    'stale_type2_row_suppressed' => !str_contains($plainText, 'Stale duplicate xref-stream page'),
    'stale_info_metadata_suppressed' => !str_contains($encodedMetadata, 'Stale duplicate metadata title'),
    'later_free_row_selected' => $review['compressed_entry_count'] === 0,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
