<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$guardContent = 'BT /F1 12 Tf 72 720 Td (Current carrier replacement guard page) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 700 Td (Stale resurrected carrier page leak) Tj T* (Old ObjStm must stay suppressed) Tj ET';
$compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale carrier replacement page) >>';

$header = '4 0';
$objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress stale carrier replacement object stream fixture.');
}

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation][] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(6, 0, '<< /Type /Metadata /Subtype /XML /Note (new non object-stream carrier replacement) >>');
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

$xrefRows = ''
    . $xrefRow(1, $offsets['1:0'][0])
    . $xrefRow(1, $offsets['2:0'][0])
    . $xrefRow(1, $offsets['3:0'][0])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['5:0'][0])
    . $xrefRow(2, 6, 0)
    . $xrefRow(1, $offsets['8:0'][0])
    . $xrefRow(1, $offsets['9:0'][0]);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress stale carrier replacement xref rows.');
}

$xrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

$smoke = [
    'native_boundary' => 'current xref type-2 carrier rows do not resurrect stale direct ObjStm storage after same-number replacement',
    'uses_current_guard_page' => $plainText === 'Current carrier replacement guard page',
    'suppresses_stale_carrier_member' => !str_contains($plainText, 'Stale resurrected carrier page leak')
        && !str_contains($plainText, 'Old ObjStm must stay suppressed')
        && !str_contains($plainText, 'stale carrier replacement page'),
    'suppresses_replacement_metadata_text' => !str_contains($plainText, 'new non object-stream carrier replacement'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'unresolved_object_stream_carrier_count' => $review['unresolved_object_stream_carrier_count'] ?? null,
    'carrier_resolved' => $entries[4]['object_stream_carrier_resolved'] ?? null,
    'owner_policy' => $entries[4]['object_stream_owner_policy'] ?? null,
    'selection_policy' => $entries[4]['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'uses_current_guard_page',
    'suppresses_stale_carrier_member',
    'suppresses_replacement_metadata_text',
] as $requiredFlag) {
    if (($smoke[$requiredFlag] ?? false) !== true) {
        throw new RuntimeException('Stale carrier replacement object-stream smoke failed: ' . $requiredFlag);
    }
}

if (($smoke['carrier_resolved'] ?? true) !== false || ($smoke['selection_policy'] ?? null) !== 'missing_object_stream_member') {
    throw new RuntimeException('Stale carrier replacement review policy was not fail-closed.');
}

echo '<!-- markerpdf-xref-object-stream-stale-carrier-replacement-currentbase-smoke ' . htmlspecialchars(json_encode(
    $smoke,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
