<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed xref operand page) Tj T* (Compressed Filter operand selected) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 704 Td (Stale compressed xref operand leak) Tj ET';

$helperMembers = [
    30 => '/FlateDecode',
    31 => 'null',
];
$helperObjectData = '';
$helperHeaderPairs = [];
$helperMemberIndexes = [];
foreach ($helperMembers as $objectNumber => $body) {
    $helperHeaderPairs[] = $objectNumber . ' ' . strlen($helperObjectData);
    $helperMemberIndexes[$objectNumber] = count($helperMemberIndexes);
    $helperObjectData .= $body . "\n";
}
$helperHeader = implode(' ', $helperHeaderPairs);
$helperObjectStream = $helperHeader . "\n" . $helperObjectData;

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(30, 0, '/ASCIIHexDecode');
$addObject(31, 0, '<< /Predictor /Twelve /Columns 1 >>');
$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($helperMembers) . ' /First ' . (strlen($helperHeader) + 1) . ' /Length ' . strlen($helperObjectStream) . " >>\nstream\n{$helperObjectStream}\nendstream");
$addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
$addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
$addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 12 0 R >>');
$addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(1, $offsets['4:0'])
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(2, 6, $helperMemberIndexes[30])
    . $xrefRow(2, 6, $helperMemberIndexes[31]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress xref-stream compressed operand smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 /Root 1 0 R /Index [1 6 30 2] /W [1 4 1] /Filter 30 0 R /DecodeParms 31 0 R /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
$entry = $review['entries'][0] ?? [];
$filterOperand = $entry['filter_operands'][0] ?? [];

echo '<!-- markerpdf-xref-stream-compressed-operand-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream Filter and DecodeParms operands can be current compressed object-stream helpers before WordPress text import',
    'uses_current_compressed_xref_operand_page' => str_contains($plainText, 'Current compressed xref operand page'),
    'selects_compressed_filter_operand' => str_contains($plainText, 'Compressed Filter operand selected'),
    'excludes_stale_compressed_xref_operand_page' => !str_contains($plainText, 'Stale compressed xref operand leak'),
    'excludes_stale_direct_filter_operand' => !str_contains($plainText, 'ASCIIHexDecode'),
    'xref_stream_count' => $review['xref_stream_count'],
    'xref_selected_operand_count' => $review['xref_selected_operand_count'],
    'unresolved_operand_count' => $review['unresolved_operand_count'],
    'decoded_entry_count' => $entry['decoded_entry_count'] ?? null,
    'filter_owner_policy' => $filterOperand['owner_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
