<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed Size page) Tj T* (Default xref range recovered) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed Size fallback leak) Tj ET';

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
        throw new RuntimeException('Unable to compress compressed-Size object-stream smoke fixture.');
    }

    return [$header, $compressed];
};

$currentMemberIndexes = [];
[$currentHeader, $currentCompressedObjectStream] = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
], $currentMemberIndexes);

$sizeHelperIndexes = [];
[$sizeHelperHeader, $sizeHelperObjectStream] = $objectStream([
    30 => '31',
], $sizeHelperIndexes);

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N ' . count($currentMemberIndexes) . ' /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
$addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($sizeHelperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($sizeHelperObjectStream) . " >>\nstream\n{$sizeHelperObjectStream}\nendstream");
$addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$xrefRows = '';
for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
    $xrefRows .= match ($objectNumber) {
        1, 2, 3, 4 => $row(2, 6, $currentMemberIndexes[$objectNumber]),
        5 => $row(1, $offsets['5:0']),
        6 => $row(1, $offsets['6:0']),
        7 => $row(1, $offsets['7:0']),
        9 => $row(1, $offsets['9:0']),
        30 => $row(2, 7, $sizeHelperIndexes[30]),
        default => $row(0, 0, 255),
    };
}
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress compressed-Size xref stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 30 0 R /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$indexReview = $extractor->extractXrefObjectStreamIndexReview($pdf);
$operandReview = $extractor->extractXrefStreamFilterLengthOwnerReview($pdf);
$indexEntries = array_column($indexReview['entries'], null, 'object_number');
$operandEntry = $operandReview['entries'][0] ?? [];
$sizeOperand = $operandEntry['size_operand'] ?? [];

$metadata = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream compressed /Size helper selects the default xref row range before object-stream extraction',
    'uses_current_compressed_size_page' => str_contains($plainText, 'Current compressed Size page'),
    'default_xref_range_recovered' => str_contains($plainText, 'Default xref range recovered'),
    'excludes_stale_size_fallback_page' => !str_contains($plainText, 'Stale compressed Size fallback leak'),
    'compressed_entry_count' => $indexReview['compressed_entry_count'],
    'catalog_object_stream' => $indexEntries[1]['object_stream'] ?? null,
    'size_helper_object_stream' => $indexEntries[30]['object_stream'] ?? null,
    'xref_stream_count' => $operandReview['xref_stream_count'],
    'indirect_size_count' => $operandReview['indirect_size_count'],
    'xref_selected_operand_count' => $operandReview['xref_selected_operand_count'],
    'unresolved_operand_count' => $operandReview['unresolved_operand_count'],
    'decoded_entry_count' => $operandEntry['decoded_entry_count'] ?? null,
    'size_operand_owner_policy' => $sizeOperand['owner_policy'] ?? null,
    'size_operand_value_preview' => $sizeOperand['value_preview'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

if (
    $metadata['uses_current_compressed_size_page'] !== true
    || $metadata['default_xref_range_recovered'] !== true
    || $metadata['excludes_stale_size_fallback_page'] !== true
    || $metadata['compressed_entry_count'] !== 5
    || $metadata['indirect_size_count'] !== 1
    || $metadata['size_operand_owner_policy'] !== 'compressed_operand_after_xref_decode'
) {
    throw new RuntimeException('Compressed xref-stream Size default-range smoke failed.');
}

echo '<!-- markerpdf-xref-stream-compressed-size-default-range-currentbase-smoke ' . htmlspecialchars(json_encode(
    $metadata,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
