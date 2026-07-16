<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$leakingContent = 'BT /F1 12 Tf 72 720 Td (Malformed negative Index compressed page leak) Tj ET';
$memberBody = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
$header = '4 0';
$objectStream = gzcompress($header . "\n" . $memberBody . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress malformed xref-index smoke object stream.');
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
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($leakingContent) . " >>\nstream\n{$leakingContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$xrefRows = ''
    . $xrefRow(0, 0, 65535)
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress malformed xref-index smoke stream.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [-1 7] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$indexEntry = $review['malformed_xref_stream_index_entries'][0] ?? [];

$summary = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'malformed xref-stream /Index values fail closed before object-stream member expansion for WordPress import',
    'malformed_index_rejected' => ($review['malformed_xref_stream_index_count'] ?? null) === 1,
    'owner_policy' => $indexEntry['owner_policy'] ?? null,
    'malformed_index_indexes' => $indexEntry['malformed_index_indexes'] ?? [],
    'compressed_entries_expanded' => $review['compressed_entry_count'] ?? null,
    'excludes_negative_index_object_stream_text' => !str_contains($plainText, 'Malformed negative Index compressed page leak'),
    'visible_text_empty' => $plainText === '',
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

foreach ([
    'malformed_index_rejected',
    'excludes_negative_index_object_stream_text',
    'visible_text_empty',
] as $requiredFlag) {
    if (($summary[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Malformed xref-stream /Index smoke failed: ' . $requiredFlag);
    }
}

if (($summary['owner_policy'] ?? null) !== 'negative_xref_stream_index_value') {
    throw new RuntimeException('Malformed xref-stream /Index smoke failed: owner_policy');
}

echo '<!-- markerpdf-parser-xref-stream-malformed-index-currentbase-smoke ' . htmlspecialchars(json_encode(
    $summary,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
