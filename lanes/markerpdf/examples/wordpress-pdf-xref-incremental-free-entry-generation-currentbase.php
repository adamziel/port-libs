<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$stalePageContent = 'BT /F1 12 Tf 72 720 Td (Stale incremental freed page) Tj ET';
$staleFallbackContent = 'BT /F1 12 Tf 72 680 Td (Stale freed content stream) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current incremental free page) Tj T* (Free generation row kept) Tj ET';

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
$addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents [5 0 R 6 0 R] >>');
$addObject(5, 0, "<< /Length " . strlen($stalePageContent) . " >>\nstream\n{$stalePageContent}\nendstream");
$addObject(6, 0, "<< /Length " . strlen($staleFallbackContent) . " >>\nstream\n{$staleFallbackContent}\nendstream");

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 7\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow($offsets['6:0'])
    . "trailer\n<< /Size 7 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$addObject(2, 1, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
$addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
$addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $xrefStreamRow(1, $offsets['1:1'], 1)
    . $xrefStreamRow(1, $offsets['2:1'], 1)
    . $xrefStreamRow(0, 5, 1)
    . $xrefStreamRow(0, 6, 1)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(1, $offsets['8:0'], 0)
    . $xrefStreamRow(1, $offsets['9:0'], 0);
$compressedCurrentRows = gzcompress($currentRows);
if (!is_string($compressedCurrentRows)) {
    throw new RuntimeException('Unable to compress current xref-stream smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 3 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
    . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-incremental-free-entry-generation-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'latest incremental xref-stream free generation rows suppress stale Prev page and content objects before Gutenberg paragraphs',
    'uses_current_incremental_page' => str_contains($plainText, 'Current incremental free page'),
    'keeps_free_generation_row' => str_contains($plainText, 'Free generation row kept'),
    'excluded_stale_prev_page' => !str_contains($plainText, 'Stale incremental freed page'),
    'excluded_stale_prev_content_stream' => !str_contains($plainText, 'Stale freed content stream'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
