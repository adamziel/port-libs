<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';
require_once __DIR__ . '/../src/PdfOutlineExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect xref array page) Tj T* (Indirect W Index owners reviewed) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect xref array leak) Tj T* (Fallback object stream expanded) Tj ET';

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
        throw new RuntimeException('Unable to compress xref array owner review object-stream smoke fixture.');
    }

    return [$header, $compressed];
};

$currentMemberIndexes = [];
$staleMemberIndexes = [];
[$currentHeader, $currentCompressedObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
], $currentMemberIndexes);
[$staleHeader, $staleCompressedObjectStream] = $objectStream([
    4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (stale fallback object stream page) >>',
], $staleMemberIndexes);

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
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
$addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleCompressedObjectStream) . " >>\nstream\n{$staleCompressedObjectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$wOffset = $addObject(30, 0, '[1 4 1]');
$indexOffset = $addObject(31, 0, '[1 6 9 1 30 3]');
$sizeOffset = $addObject(32, 0, '33');

$xrefRows = ''
    . $row(1, $offsets['1:0'])
    . $row(1, $offsets['2:0'])
    . $row(1, $offsets['3:0'])
    . $row(2, 6, $currentMemberIndexes[4])
    . $row(1, $offsets['5:0'])
    . $row(1, $offsets['6:0'])
    . $row(1, $offsets['9:0'])
    . $row(1, $wOffset)
    . $row(1, $indexOffset)
    . $row(1, $sizeOffset);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress indirect W/Index owner review xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 32 0 R /Root 1 0 R /Index 31 0 R /W 30 0 R /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$objectStreamReview = $extractor->extractXrefObjectStreamIndexReview($pdf);
$operandReview = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
$operandEntry = $operandReview['entries'][0] ?? [];
$wOperand = $operandEntry['w_operand'] ?? [];
$indexOperand = $operandEntry['index_operand'] ?? [];
$sizeOperand = $operandEntry['size_operand'] ?? [];
$metadata = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream indirect W, Index, and Size operands are review-visible only when selected by the current xref stream',
    'uses_current_indirect_xref_array_page' => str_contains($plainText, 'Current indirect xref array page'),
    'index_width_owners_reviewed' => str_contains($plainText, 'Indirect W Index owners reviewed'),
    'excluded_stale_indirect_xref_array_page' => !str_contains($plainText, 'Stale indirect xref array leak'),
    'excluded_stale_object_stream_payload' => !str_contains($plainText, 'Fallback object stream expanded'),
    'compressed_entry_count' => $objectStreamReview['compressed_entry_count'],
    'xref_stream_count' => $operandReview['xref_stream_count'],
    'indirect_w_count' => $operandReview['indirect_w_count'],
    'indirect_index_count' => $operandReview['indirect_index_count'],
    'indirect_size_count' => $operandReview['indirect_size_count'],
    'xref_selected_operand_count' => $operandReview['xref_selected_operand_count'],
    'w_owner_policy' => $wOperand['owner_policy'] ?? null,
    'index_owner_policy' => $indexOperand['owner_policy'] ?? null,
    'size_owner_policy' => $sizeOperand['owner_policy'] ?? null,
    'decoded_with_current_operands' => $operandEntry['decoded_with_current_operands'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

if (
    $metadata['uses_current_indirect_xref_array_page'] !== true
    || $metadata['index_width_owners_reviewed'] !== true
    || $metadata['excluded_stale_indirect_xref_array_page'] !== true
    || $metadata['excluded_stale_object_stream_payload'] !== true
    || $metadata['compressed_entry_count'] !== 1
    || $metadata['indirect_w_count'] !== 1
    || $metadata['indirect_index_count'] !== 1
    || $metadata['indirect_size_count'] !== 1
    || $metadata['xref_selected_operand_count'] !== 3
    || $metadata['w_owner_policy'] !== 'xref_selected_direct_object'
    || $metadata['index_owner_policy'] !== 'xref_selected_direct_object'
    || $metadata['size_owner_policy'] !== 'xref_selected_direct_object'
    || $metadata['decoded_with_current_operands'] !== true
) {
    throw new RuntimeException('Xref stream W/Index owner review smoke did not preserve the current-base owner boundary.');
}

echo '<!-- markerpdf-xref-stream-index-width-owner-review-currentbase-smoke ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
