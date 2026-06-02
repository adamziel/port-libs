<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid Prev size page) Tj T* (Trailer size repaired row) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale oversized trailer page) Tj ET';

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
$addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
$addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$previousXrefRows = ''
    . $xrefStreamRow(0, 0, 65535)
    . $xrefStreamRow(1, $offsets['1:0'], 0)
    . $xrefStreamRow(1, $offsets['2:0'], 0)
    . $xrefStreamRow(1, $offsets['3:0'], 0)
    . $xrefStreamRow(1, $offsets['4:0'], 0)
    . $xrefStreamRow(1, $offsets['5:0'], 0);
$compressedPreviousXref = gzcompress($previousXrefRows);
if (!is_string($compressedPreviousXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}

$previousXrefOffset = $addObject(
    6,
    0,
    '<< /Type /XRef /Size 4 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedPreviousXref) . " >>\nstream\n{$compressedPreviousXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(5, 1, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$hybridRows = $xrefStreamRow(1, 0, 0);
$compressedHybridRows = gzcompress($hybridRows);
if (!is_string($compressedHybridRows)) {
    throw new RuntimeException('Unable to compress hybrid xref-stream smoke fixture.');
}

$hybridXrefOffset = $addObject(
    8,
    0,
    '<< /Type /XRef /Size 9 /Index [8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridRows) . " >>\nstream\n{$compressedHybridRows}\nendstream"
);

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 1\n" . $xrefTableRow(0, 65535, 'f')
    . "8 1\n" . $xrefTableRow($hybridXrefOffset)
    . "trailer\n<< /Size 6 /Root 1 0 R /Prev {$previousXrefOffset} /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-hybrid-prev-trailer-size-repair-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'hybrid xref table /Prev chain repairs an underdeclared xref-stream trailer Size from exact decoded row count',
    'uses_current_hybrid_prev_size_page' => str_contains($plainText, 'Current hybrid Prev size page'),
    'repairs_underdeclared_trailer_size' => str_contains($plainText, 'Trailer size repaired row'),
    'excluded_stale_oversized_trailer_page' => !str_contains($plainText, 'Stale oversized trailer page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
