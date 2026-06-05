<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objectStream = static function (array $members): array {
    $headerPairs = [];
    $indexes = [];
    $data = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($data);
        $indexes[$objectNumber] = count($indexes);
        $data .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $content = gzcompress($header . "\n" . $data);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to compress object-stream Prev helper fixture.');
    }

    return [
        'count' => count($members),
        'first' => strlen($header) + 1,
        'indexes' => $indexes,
        'content' => $content,
    ];
};

$previousPayload = '<wp-export><post id="previous-compressed-prev-summary"/></wp-export>';
$currentPayload = '<wp-export><post id="current-compressed-prev-summary"/></wp-export>';

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$previousCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Names << /EmbeddedFiles 8 0 R >> >>');
$previousNameTreeOffset = $addObject(8, 0, '<< /Names [(previous-compressed-prev-summary.xml) 10 0 R] >>');
$previousFileSpecOffset = $addObject(10, 0, '<< /Type /Filespec /F (previous-compressed-prev-summary.xml) /Desc (Previous compressed Prev summary attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$previousEmbeddedOffset = $addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($previousPayload) . " >>\nstream\n{$previousPayload}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 12\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($previousCatalogOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($previousNameTreeOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($previousFileSpecOffset)
    . $xrefTableRow($previousEmbeddedOffset)
    . "trailer\n<< /Size 12 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Names << /EmbeddedFiles 8 0 R >> >>');
$addObject(8, 0, '<< /Names [(current-compressed-prev-summary.xml) 10 0 R] >>');
$addObject(10, 0, '<< /Type /Filespec /F (current-compressed-prev-summary.xml) /Desc (Current compressed Prev summary attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

$prevHelperStream = $objectStream([30 => (string) $previousXrefOffset]);
$prevHelperCarrierOffset = $addObject(
    90,
    0,
    '<< /Type /ObjStm /N ' . $prevHelperStream['count'] . ' /First ' . $prevHelperStream['first'] . ' /Filter /FlateDecode /Length ' . strlen($prevHelperStream['content']) . " >>\nstream\n{$prevHelperStream['content']}\nendstream"
);

$rows = ''
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(1, 0, 0)
    . $xrefStreamRow(2, 90, $prevHelperStream['indexes'][30])
    . $xrefStreamRow(1, $prevHelperCarrierOffset, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress xref rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev 30 0 R /Index [1 1 8 1 10 2 30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$encoded = json_encode([$summary, $files], JSON_UNESCAPED_SLASHES);

$checks = [
    'xref_stream_uses_compressed_prev_helper' => str_contains($pdf, '/Prev 30 0 R') && str_contains($pdf, '/Type /ObjStm'),
    'summary_selects_current_attachment' => $summary['attachment_count'] === 1
        && $summary['filenames'] === ['current-compressed-prev-summary.xml'],
    'summary_reports_native_payload_metadata' => ($summary['attachments'][0]['byte_length'] ?? null) === strlen($currentPayload)
        && ($summary['attachments'][0]['sha256'] ?? null) === hash('sha256', $currentPayload),
    'embedded_file_extracts_current_payload' => ($files[0]['content'] ?? null) === $currentPayload,
    'previous_attachment_suppressed' => is_string($encoded) && !str_contains($encoded, 'previous-compressed-prev-summary'),
    'no_external_execution' => $summary['executes_python_or_models'] === false
        && $summary['executes_external_pdf_tools'] === false,
];

foreach ($checks as $name => $passed) {
    if ($passed !== true) {
        throw new RuntimeException("Compressed Prev attachment summary smoke failed: {$name}");
    }
}

echo json_encode([
    'scenario' => 'wordpress_pdf_xref_prev_compressed_helper_attachment_summary_currentbase',
    'attachment_count' => $summary['attachment_count'],
    'filenames' => $summary['filenames'],
    'total_bytes' => $summary['total_bytes'],
    'sha256' => $summary['attachments'][0]['sha256'] ?? null,
    'compressed_prev_helper' => true,
    'native_pdf_boundary' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
