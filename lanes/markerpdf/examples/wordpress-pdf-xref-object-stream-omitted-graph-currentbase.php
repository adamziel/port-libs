<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale omitted graph page) Tj T* (Previous xref row leaked) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current omitted graph page) Tj T* (Reachable compressed members repaired) Tj ET';

$pdf = "%PDF-1.7\n";
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
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$objectStream = static function (array $members): array {
    $headerPairs = [];
    $indexes = [];
    $body = '';
    foreach ($members as $objectNumber => $memberBody) {
        $headerPairs[] = $objectNumber . ' ' . strlen($body);
        $indexes[$objectNumber] = count($indexes);
        $body .= $memberBody . "\n";
    }

    $header = implode(' ', $headerPairs);
    $compressed = gzcompress($header . "\n" . $body);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress omitted graph object-stream smoke fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 6\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($stalePagesOffset)
    . $xrefTableRow($stalePageOffset)
    . $xrefTableRow($staleContentOffset)
    . $xrefTableRow($fontOffset)
    . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentCarrier = $objectStream([
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
]);
$currentCarrierOffset = $addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . $currentCarrier['count'] . ' /First ' . $currentCarrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($currentCarrier['content']) . " >>\nstream\n{$currentCarrier['content']}\nendstream"
);

$currentRows = ''
    . $xrefStreamRow(2, 20, $currentCarrier['indexes'][1])
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, $currentCarrierOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress omitted graph xref-stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 5 1 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 xref-stream compressed catalog repairs reachable omitted ObjStm page-tree members before Prev rows',
    'current_text_selected' => str_contains($plainText, 'Current omitted graph page')
        && str_contains($plainText, 'Reachable compressed members repaired'),
    'stale_prev_text_suppressed' => !str_contains($plainText, 'Stale omitted graph page')
        && !str_contains($plainText, 'Previous xref row leaked'),
    'current_carrier_present' => str_contains($pdf, '/Type /ObjStm'),
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'object_2_owner_policy' => $entries[2]['object_stream_owner_policy'] ?? null,
    'object_2_selection_policy' => $entries[2]['selection_policy'] ?? null,
    'object_3_owner_policy' => $entries[3]['object_stream_owner_policy'] ?? null,
    'object_3_selection_policy' => $entries[3]['selection_policy'] ?? null,
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
];

echo '<!-- markerpdf-xref-object-stream-omitted-graph-currentbase-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

if ($smoke['current_text_selected'] !== true || $smoke['stale_prev_text_suppressed'] !== true) {
    throw new RuntimeException('Omitted object-stream graph repair did not select current WordPress text.');
}
if ($smoke['compressed_entry_count'] !== 3) {
    throw new RuntimeException('Expected three compressed object-stream entries after omitted graph repair.');
}
if (
    $smoke['object_2_owner_policy'] !== 'xref_selected_object_stream_carrier'
    || $smoke['object_3_owner_policy'] !== 'xref_selected_object_stream_carrier'
) {
    throw new RuntimeException('Expected omitted page-tree members to resolve through the xref-selected carrier.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must stay native PHP without models or external PDF tools.');
}
