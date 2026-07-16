<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale misindexed Prev page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation Index repair page) Tj T* (Offset owner row repaired) Tj ET';

$pdf = "%PDF-1.5\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $type, int $offset, int $generation): string => chr($type) . pack('N', $offset) . chr($generation);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

$previousRows = ''
    . $xrefRow(1, $offsets['1:0'], 0)
    . $xrefRow(1, $offsets['2:0'], 0)
    . $xrefRow(1, $offsets['3:0'], 0)
    . $xrefRow(1, $offsets['4:0'], 0)
    . $xrefRow(1, $offsets['5:0'], 0);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream Index-repair smoke fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 1 R >>');
$addObject(4, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . $xrefRow(1, $offsets['1:0'], 0)
    . $xrefRow(1, $offsets['2:0'], 0)
    . $xrefRow(1, $offsets['3:0'], 0)
    . $xrefRow(1, $offsets['4:1'], 1);
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream Index-repair smoke fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [10 4] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-generation-index-repair-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream rows with malformed sparse /Index object numbers are repaired by explicit direct-object offsets before older /Prev rows',
    'uses_current_generation_index_repair_page' => str_contains($plainText, 'Current generation Index repair page'),
    'repairs_offset_owner_row' => str_contains($plainText, 'Offset owner row repaired'),
    'excluded_stale_misindexed_prev_page' => !str_contains($plainText, 'Stale misindexed Prev page'),
    'page_count' => $extractor->extractOutlineMetadata($pdf)['pages'],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
