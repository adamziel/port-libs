<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale root generation zero page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Prev hybrid size generation page) Tj T* (Root generation repaired) Tj ET';

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
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
$addObject(5, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$previousXrefRows = ''
    . $xrefStreamRow(0, 0, 65535)
    . $xrefStreamRow(1, $offsets['1:1'], 1)
    . $xrefStreamRow(1, $offsets['2:1'], 1)
    . $xrefStreamRow(1, $offsets['3:1'], 1)
    . $xrefStreamRow(1, $offsets['4:0'], 0)
    . $xrefStreamRow(1, $offsets['5:1'], 1);
$compressedPreviousXref = gzcompress($previousXrefRows);
if (!is_string($compressedPreviousXref)) {
    throw new RuntimeException('Unable to compress previous xref-stream smoke fixture.');
}

$previousXrefOffset = $addObject(
    6,
    0,
    '<< /Type /XRef /Size 3 /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedPreviousXref) . " >>\nstream\n{$compressedPreviousXref}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

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
    . "0 4\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . "5 1\n" . $xrefTableRow($offsets['5:0'])
    . "8 1\n" . $xrefTableRow($hybridXrefOffset)
    . "trailer\n<< /Size 9 /Root 1 1 R /Prev {$previousXrefOffset} /XRefStm {$hybridXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-prev-hybrid-size-generation-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'hybrid xref table /Prev chain preserves underdeclared xref-stream Size repair and trailer Root generation',
    'uses_current_prev_hybrid_size_generation_page' => str_contains($plainText, 'Current Prev hybrid size generation page'),
    'repairs_root_generation_reference' => str_contains($plainText, 'Root generation repaired'),
    'excludes_stale_root_generation_zero_page' => !str_contains($plainText, 'Stale root generation zero page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
