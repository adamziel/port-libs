<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$directContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace-offset guard page) Tj ET';
$leakContent = 'BT /F1 12 Tf 72 700 Td (Whitespace-offset compressed leak) Tj T* (Whitespace-owned member ignored) Tj ET';
$carrierBody = '<< /Type /Catalog /Pages 2 0 R >>';
$fakePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$objectData = $carrierBody . "\n" . $fakePage . "\n";
$badOffset = strlen($carrierBody);
$header = '12 0 4 ' . $badOffset;
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress whitespace-offset object-stream smoke fixture.');
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
$addObject(5, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 1)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0'])
    . $xrefRow(2, 6, 0);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress whitespace-offset xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$text = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

if (
    $lines !== ['Current whitespace-offset guard page']
    || str_contains($text, 'Whitespace-offset compressed leak')
    || str_contains($text, 'Whitespace-owned member ignored')
    || ($review['invalid_member_offset_rejection_count'] ?? null) !== 1
    || ($entry['selection_policy'] ?? null) !== 'invalid_object_stream_member_offset'
    || ($entry['member_offset_token_boundary'] ?? null) !== false
) {
    throw new RuntimeException('Expected whitespace-owned object-stream member offset to be rejected before WordPress import.');
}

echo '<!-- markerpdf-xref-object-stream-whitespace-offset-currentbase-smoke ' . htmlspecialchars(json_encode([
    'native_boundary' => 'xref-selected object-stream member offsets must land on object tokens, not PDF whitespace before a token',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'text_lines' => $lines,
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'invalid_member_offset_rejection_count' => $review['invalid_member_offset_rejection_count'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
    'compressed_leak_excluded' => !str_contains($text, 'Whitespace-offset compressed leak'),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($lines[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
