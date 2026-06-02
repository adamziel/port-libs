<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref stream page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref stream page) Tj T* (Width default import) Tj ET';

$pdf = "%PDF-1.5\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    return $offset;
};

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$fontOffset = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$staleContentOffset = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

$staleRows = pack('N', $staleCatalogOffset)
    . pack('N', $stalePagesOffset)
    . pack('N', $stalePageOffset)
    . pack('N', $fontOffset)
    . pack('N', $staleContentOffset);
$staleCompressed = gzcompress($staleRows);
$addObject(20, 0, '<< /Type /XRef /Size 21 /Index [1 5] /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($staleCompressed) . " >>\nstream\n{$staleCompressed}\nendstream");

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
$currentContentOffset = $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentRows = ''
    . pack('N', 0)
    . pack('N', $currentCatalogOffset)
    . pack('N', $currentPagesOffset)
    . pack('N', $currentPageOffset)
    . pack('N', $fontOffset)
    . pack('N', $currentContentOffset);
$currentCompressed = gzcompress($currentRows);
$currentXrefOffset = strlen($pdf);
$pdf .= "6 0 obj\n"
    . '<< /Type /XRef /Size 6 /Root 1 0 R /W [0 4 0] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
    . "stream\n{$currentCompressed}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-xref-stream-index-width-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF 1.5 xref stream /Index ordering plus zero-width /W default field handling',
    'uses_current_xref_stream' => str_contains($plainText, 'Current xref stream page'),
    'uses_default_index_range' => str_contains($plainText, 'Width default import'),
    'excluded_stale_rebuilt_page' => !str_contains($plainText, 'Stale xref stream page'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
