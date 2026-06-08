<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect XRefStm table page) Tj T* (Hybrid stream offset helper ignored) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect XRefStm object-stream page) Tj T* (Hybrid stream offset helper resolved) Tj ET';

$members = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
];
$objectData = '';
$headerPairs = [];
$memberIndexes = [];
foreach ($members as $objectNumber => $body) {
    $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
    $memberIndexes[$objectNumber] = count($memberIndexes);
    $objectData .= $body . "\n";
}
$header = implode(' ', $headerPairs);
$objectStream = gzcompress($header . "\n" . $objectData);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress indirect-XRefStm object-stream smoke fixture.');
}

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$currentContentOffset = $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$carrierOffset = $addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream"
);

$xrefRows = ''
    . $xrefStreamRow(2, 20, $memberIndexes[1])
    . $xrefStreamRow(2, 20, $memberIndexes[2])
    . $xrefStreamRow(2, 20, $memberIndexes[3])
    . $xrefStreamRow(1, $fontOffset)
    . $xrefStreamRow(1, $currentContentOffset)
    . $xrefStreamRow(1, $carrierOffset);
$compressedXref = gzcompress($xrefRows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress indirect-XRefStm xref stream smoke rows.');
}

$xrefStreamOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 5 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n";
$xrefStmHelperOffset = $addObject(30, 0, (string) $xrefStreamOffset);

$xrefTableOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefTableRow(0, 65535, 'f')
    . "4 1\n"
    . $xrefTableRow($fontOffset)
    . "20 2\n"
    . $xrefTableRow($carrierOffset)
    . $xrefTableRow($xrefStreamOffset)
    . "30 1\n"
    . $xrefTableRow($xrefStmHelperOffset)
    . "trailer\n<< /Size 31 /Root 1 0 R /XRefStm 30 0 R >>\n"
    . "startxref\n{$xrefTableOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 hybrid xref table with indirect /XRefStm helper selects object-stream catalog members',
    'current_text_selected' => str_contains($plainText, 'Current indirect XRefStm object-stream page')
        && str_contains($plainText, 'Hybrid stream offset helper resolved'),
    'stale_scanned_text_suppressed' => !str_contains($plainText, 'Stale indirect XRefStm table page')
        && !str_contains($plainText, 'Hybrid stream offset helper ignored'),
    'indirect_xrefstm_reference_present' => str_contains($pdf, '/XRefStm 30 0 R'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'object_1_owner_policy' => $entries[1]['object_stream_owner_policy'] ?? null,
    'object_1_selection_policy' => $entries[1]['selection_policy'] ?? null,
    'object_2_owner_policy' => $entries[2]['object_stream_owner_policy'] ?? null,
    'object_2_selection_policy' => $entries[2]['selection_policy'] ?? null,
    'object_3_owner_policy' => $entries[3]['object_stream_owner_policy'] ?? null,
    'object_3_selection_policy' => $entries[3]['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

echo '<!-- markerpdf-xref-hybrid-indirect-xrefstm-currentbase-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

if ($smoke['current_text_selected'] !== true || $smoke['stale_scanned_text_suppressed'] !== true) {
    throw new RuntimeException('Indirect hybrid XRefStm object-stream smoke did not select current WordPress text.');
}
if ($smoke['compressed_entry_count'] !== 3) {
    throw new RuntimeException('Expected three compressed object-stream entries from the indirect hybrid XRefStm stream.');
}
foreach ([1, 2, 3] as $objectNumber) {
    if (($entries[$objectNumber]['object_stream_owner_policy'] ?? null) !== 'xref_selected_object_stream_carrier') {
        throw new RuntimeException('Expected object ' . $objectNumber . ' to resolve through the xref-selected carrier.');
    }
    if (($entries[$objectNumber]['selection_policy'] ?? null) !== 'explicit_member_index') {
        throw new RuntimeException('Expected object ' . $objectNumber . ' to keep the explicit member index.');
    }
}
