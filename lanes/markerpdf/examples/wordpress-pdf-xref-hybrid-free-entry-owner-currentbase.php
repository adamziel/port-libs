<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid table direct page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid free owner page) Tj T* (Hybrid xref stream free row wins) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale table row should be freed by hybrid stream) >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$hybridRows = $xrefStreamRow(0, 0, 2);
$hybridXrefStream = gzcompress($hybridRows);
if (!is_string($hybridXrefStream)) {
    throw new RuntimeException('Unable to compress hybrid free-entry xref stream smoke fixture.');
}

$hybridXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($hybridXrefStream) . " >>\nstream\n{$hybridXrefStream}\nendstream"
);

$xrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($offsets['8:0'])
    . $xrefTableRow($offsets['9:0'])
    . "trailer\n<< /Size 21 /Root 1 0 R /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractXrefObjectStreamIndexReview($pdf);
$freeEntry = $review['free_entries'][0] ?? [];

echo '<!-- markerpdf-xref-hybrid-free-entry-owner-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'current hybrid xref-stream type-0 free rows own object numbers before compatibility xref table direct rows',
    'uses_current_hybrid_free_owner_page' => str_contains($plainText, 'Current hybrid free owner page'),
    'reports_hybrid_xref_stream_free_owner' => ($review['xref_stream_free_owner_count'] ?? 0) === 1,
    'suppresses_table_direct_owner' => ($freeEntry['owner_policy'] ?? null) === 'hybrid_xref_stream_free_entry_suppressed_table_direct_object',
    'excluded_stale_table_direct_page' => !str_contains($plainText, 'Stale hybrid table direct page'),
    'excluded_stale_table_note' => !str_contains($plainText, 'stale table row should be freed by hybrid stream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
