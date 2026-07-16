<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale previous stream generation page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current generation stream page) Tj T* (Offset repaired generation) Tj ET';

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefStreamRows = static function (array $offsets, int $generationWidth = 1): string {
    $rows = '';
    foreach ($offsets as $offset) {
        $rows .= chr(1) . pack('N', $offset);
        if ($generationWidth > 0) {
            $rows .= chr(0);
        }
    }

    return $rows;
};

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleContentOffset = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$previousRows = $xrefStreamRows([
    $staleCatalogOffset,
    $stalePagesOffset,
    $stalePageOffset,
    $fontOffset,
    $staleContentOffset,
]);
$previousCompressed = gzcompress($previousRows);
if (!is_string($previousCompressed)) {
    throw new RuntimeException('Unable to compress previous xref-stream fixture.');
}

$previousXrefOffset = $addObject(
    20,
    0,
    '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
);
$pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
$currentPagesOffset = $addObject(2, 1, '<< /Type /Pages /Kids [3 1 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 1 R >>');
$currentContentOffset = $addObject(5, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = $xrefStreamRows([
    $currentCatalogOffset,
    $currentPagesOffset,
    $currentPageOffset,
    $currentContentOffset,
], 0);
$currentCompressed = gzcompress($currentRows);
if (!is_string($currentCompressed)) {
    throw new RuntimeException('Unable to compress current xref-stream fixture.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 3 5 1] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-prev-stream-generation-repair-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF xref-stream /Prev chain direct-object generation repair by exact xref byte offsets',
    'uses_current_generation_stream_page' => str_contains($plainText, 'Current generation stream page'),
    'repairs_zero_width_generation_by_offset' => str_contains($plainText, 'Offset repaired generation'),
    'excluded_previous_stream_generation_page' => !str_contains($plainText, 'Stale previous stream generation page'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
