<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$guardContent = 'BT /F1 12 Tf 72 720 Td (Current wrapper guard page) Tj ET';
$leakContent = 'BT /F1 12 Tf 72 700 Td (Wrapped omitted graph leak) Tj T* (Indirect wrapper graph ignored) Tj ET';

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
        throw new RuntimeException('Unable to compress omitted graph indirect-wrapper object stream smoke fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $compressed,
    ];
};

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n"
    . $xrefTableRow(0, 65535, 'f')
    . "trailer\n<< /Size 1 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$guardPageOffset = $addObject(8, 0, '<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 9 0 R >>');
$guardContentOffset = $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");
$leakContentOffset = $addObject(10, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
$carrier = $objectStream([
    1 => '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 10 0 R >>',
]);
$carrierOffset = $addObject(
    20,
    0,
    '<< /Type /ObjStm /N ' . $carrier['count'] . ' /First ' . $carrier['first'] . ' /Filter /FlateDecode /Length ' . strlen($carrier['content']) . " >>\nstream\n{$carrier['content']}\nendstream"
);

$currentRows = ''
    . $xrefStreamRow(2, 20, $carrier['indexes'][1])
    . $xrefStreamRow(1, $fontOffset, 0)
    . $xrefStreamRow(1, $guardPageOffset, 0)
    . $xrefStreamRow(1, $guardContentOffset, 0)
    . $xrefStreamRow(1, $leakContentOffset, 0)
    . $xrefStreamRow(1, $carrierOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress omitted graph indirect-wrapper xref-stream smoke rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 1 5 1 8 3 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$entries = array_column($review['entries'], null, 'object_number');
$entry = $entries[1] ?? [];

$smoke = [
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream omitted graph repair rejects indirect-object wrappers inside object streams',
    'guard_page_selected' => $lines === ['Current wrapper guard page'],
    'wrapped_omitted_graph_suppressed' => !str_contains($plainText, 'Wrapped omitted graph leak')
        && !str_contains($plainText, 'Indirect wrapper graph ignored'),
    'compressed_page_tree_not_repaired_from_wrapper' => $extractor->extractOutlineMetadata($pdf)['pages'] === 1,
    'compressed_entry_count' => $review['compressed_entry_count'] ?? null,
    'indirect_member_wrapper_rejection_count' => $review['indirect_member_wrapper_rejection_count'] ?? null,
    'object_1_selection_policy' => $entry['selection_policy'] ?? null,
    'object_1_wrapper_rejected' => $entry['indirect_object_wrapper_rejected'] ?? null,
];

echo '<!-- markerpdf-xref-object-stream-omitted-graph-indirect-wrapper-currentbase-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

if ($smoke['guard_page_selected'] !== true) {
    throw new RuntimeException('Expected the direct guard page to remain the only WordPress paragraph.');
}
if ($smoke['wrapped_omitted_graph_suppressed'] !== true) {
    throw new RuntimeException('Indirect-wrapper object-stream graph leaked into WordPress text.');
}
if ($smoke['compressed_page_tree_not_repaired_from_wrapper'] !== true) {
    throw new RuntimeException('Indirect-wrapper object-stream member repaired the page tree.');
}
if ($smoke['compressed_entry_count'] !== 1 || $smoke['indirect_member_wrapper_rejection_count'] !== 1) {
    throw new RuntimeException('Expected the xref object-stream review to record one rejected compressed wrapper.');
}
if (
    $smoke['object_1_selection_policy'] !== 'indirect_object_wrapper_member'
    || $smoke['object_1_wrapper_rejected'] !== true
) {
    throw new RuntimeException('Expected object 1 to be rejected as an indirect-object wrapper member.');
}
if ($smoke['executes_python_or_models'] !== false || $smoke['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Example must stay native PHP without models or external PDF tools.');
}
