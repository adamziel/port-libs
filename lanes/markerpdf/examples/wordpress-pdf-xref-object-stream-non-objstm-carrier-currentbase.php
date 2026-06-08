<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current non-ObjStm carrier guard page) Tj T* (Metadata stream is not an object stream) Tj ET';
$leakedContent = 'BT /F1 12 Tf 72 700 Td (Non object-stream carrier leak) Tj T* (Metadata bytes decoded as compressed objects) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (metadata carrier must not provide this page) >>';
$header = '4 0';
$metadataPayload = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($metadataPayload)) {
    throw new RuntimeException('Unable to compress non-ObjStm carrier smoke fixture.');
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
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($leakedContent) . " >>\nstream\n{$leakedContent}\nendstream");
$addObject(6, 0, '<< /Type /Metadata /Subtype /XML /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'])
    . $xrefRow(1, $offsets['2:0'])
    . $xrefRow(1, $offsets['3:0'])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'])
    . $xrefRow(1, $offsets['6:0'])
    . $xrefRow(1, $offsets['8:0'])
    . $xrefRow(1, $offsets['9:0']);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress non-ObjStm carrier xref-stream smoke fixture.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[4] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'xref-stream type-2 rows must point at a direct /ObjStm carrier, not a metadata stream with /N and /First bytes',
    'uses_current_guard_page' => str_contains($plainText, 'Current non-ObjStm carrier guard page'),
    'metadata_stream_not_imported_as_page' => !str_contains($plainText, 'Non object-stream carrier leak'),
    'metadata_member_note_suppressed' => !str_contains($plainText, 'metadata carrier must not provide this page'),
    'compressed_entry_count' => $review['compressed_entry_count'],
    'unresolved_object_stream_carrier_count' => $review['unresolved_object_stream_carrier_count'],
    'invalid_explicit_object_stream_carrier_count' => $review['invalid_explicit_object_stream_carrier_count'],
    'object_stream_carrier_is_objstm' => $entry['object_stream_carrier_is_objstm'] ?? null,
    'object_stream_carrier_resolved' => $entry['object_stream_carrier_resolved'] ?? null,
    'invalid_object_stream_carrier_rejected' => ($entry['invalid_object_stream_carrier_rejected'] ?? null) === true,
    'object_stream_owner_policy' => $entry['object_stream_owner_policy'] ?? null,
    'selection_policy' => $entry['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

foreach ([
    'uses_current_guard_page',
    'metadata_stream_not_imported_as_page',
    'metadata_member_note_suppressed',
    'invalid_object_stream_carrier_rejected',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Non-ObjStm carrier smoke failed: ' . $requiredFlag);
    }
}
if ($smoke['object_stream_carrier_is_objstm'] !== false) {
    throw new RuntimeException('Expected metadata carrier to be reported as non-ObjStm.');
}
if ($smoke['object_stream_carrier_resolved'] !== false) {
    throw new RuntimeException('Expected metadata carrier to remain unresolved.');
}
if ($smoke['unresolved_object_stream_carrier_count'] !== 1) {
    throw new RuntimeException('Expected one unresolved object-stream carrier row.');
}
if ($smoke['object_stream_owner_policy'] !== 'missing_object_stream_carrier') {
    throw new RuntimeException('Expected missing object-stream carrier owner policy.');
}

echo '<!-- markerpdf-xref-object-stream-non-objstm-carrier-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
