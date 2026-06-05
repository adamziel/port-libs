<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$compressedContent = 'BT /F1 12 Tf 72 720 Td (Current repaired free-carrier object stream page) Tj T* (Type two row keeps current ObjStm) Tj ET';
$directContent = 'BT /F1 12 Tf 72 700 Td (Direct guard page after carrier repair) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$header = '4 0';
$objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress WordPress current free-carrier object stream fixture.');
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
$addObject(5, 0, "<< /Length " . strlen($compressedContent) . " >>\nstream\n{$compressedContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(0, 0, 1)
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress WordPress current free-carrier xref stream fixture.');
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

if (
    $lines !== [
        'Current repaired free-carrier object stream page',
        'Type two row keeps current ObjStm',
        'Direct guard page after carrier repair',
    ]
    || ($entry['object_stream_owner_policy'] ?? null) !== 'xref_selected_object_stream_carrier'
    || ($entry['object_stream_xref_entry_type'] ?? null) !== 1
    || str_contains($plainText, 'Type /Page')
) {
    throw new RuntimeException('Expected current free-carrier object stream repair before WordPress paragraph rendering.');
}

echo '<!-- markerpdf:pdf-xref-object-stream-current-free-carrier ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-object-stream-current-free-carrier-repair',
    'paragraphs' => $lines,
    'compressed_entry_count' => $review['compressed_entry_count'],
    'object_stream_owner_policy' => $entry['object_stream_owner_policy'] ?? null,
    'object_stream_xref_entry_type' => $entry['object_stream_xref_entry_type'] ?? null,
    'free_carrier_row_repaired' => ($entry['object_stream_xref_entry_type'] ?? null) === 1,
    'compressed_page_selected' => str_contains($plainText, 'Current repaired free-carrier object stream page'),
    'direct_page_selected' => str_contains($plainText, 'Direct guard page after carrier repair'),
    'member_dictionary_hidden' => !str_contains($plainText, 'Type /Page'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
