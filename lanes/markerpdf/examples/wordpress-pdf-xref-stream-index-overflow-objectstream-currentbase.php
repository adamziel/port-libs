<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hugeObjectNumber = '922337203685477580899';
$leakingContent = 'BT /F1 12 Tf 72 720 Td (Oversized Index object-stream root leak) Tj T* (Integer alias page imported) Tj ET';
$compressedCatalog = '<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /DisplayDocTitle true >> >>';
$header = $hugeObjectNumber . ' 0';
$objectStream = gzcompress($header . "\n" . $compressedCatalog . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress overflow xref-index object stream.');
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

$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($leakingContent) . " >>\nstream\n{$leakingContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$compressedXref = gzcompress($xrefRow(2, 6, 0));
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress overflow xref-index rows.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root ' . $hugeObjectNumber . ' 0 R /Index [' . $hugeObjectNumber . ' 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$lines = $extractor->extractTextLines($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$indexEntry = $review['malformed_xref_stream_index_entries'][0] ?? [];
$outline = $extractor->extractOutlineMetadata($pdf);

$smoke = [
    'native_boundary' => 'oversized xref-stream Index object numbers cannot alias to PHP_INT_MAX object-stream roots before WordPress import',
    'malformed_index_rejected' => ($review['malformed_xref_stream_index_count'] ?? null) === 1,
    'blocks_object_stream_root_alias' => $plainText === '' && $lines === [] && ($outline['pages'] ?? null) === 0,
    'excluded_oversized_root_text' => !str_contains($plainText, 'Oversized Index object-stream root leak')
        && !str_contains($plainText, 'Integer alias page imported'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'index_owner_policy' => $indexEntry['owner_policy'] ?? null,
    'malformed_index_indexes' => $indexEntry['malformed_index_indexes'] ?? [],
    'rejected_before_row_decode' => $indexEntry['rejected_before_row_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'malformed_index_rejected',
    'blocks_object_stream_root_alias',
    'excluded_oversized_root_text',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Overflow xref Index object-stream smoke failed: ' . $requiredFlag);
    }
}

if (
    $smoke['compressed_entry_count'] !== 0
    || $smoke['index_owner_policy'] !== 'non_integer_xref_stream_index_value'
    || $smoke['malformed_index_indexes'] !== [0]
    || $smoke['rejected_before_row_decode'] !== true
) {
    throw new RuntimeException('Overflow xref Index review policy was not fail-closed.');
}

echo '<!-- markerpdf-xref-stream-index-overflow-objectstream-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
