<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous duplicate free annotation import) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate free annotation import) Tj ET';

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

$catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 320 718] /Contents (Stale duplicate free annotation import) /A << /S /URI /URI (https://stale.example.com/duplicate-free-import) >> >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($catalogOffset)
    . $xrefTableRow($pagesOffset)
    . $xrefTableRow($previousPageOffset)
    . $xrefTableRow($previousContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleAnnotationOffset)
    . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$currentRows = ''
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(0, 0, 1)
    . $xrefStreamRow(1, $staleAnnotationOffset, 0)
    . $xrefStreamRow(1, $currentXrefOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress duplicate free-row xref-stream smoke fixture.');
}

$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [3 2 7 1 7 1 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$encodedReview = json_encode([$freeObjects, $links, $annotations], JSON_UNESCAPED_SLASHES) ?: '';

$currentTextImported = $lines === ['Current duplicate free annotation import'];
$duplicateFreeRowPreserved = isset($freeObjects[7]) && str_contains($pdf, '/Index [3 2 7 1 7 1 20 1]');
$staleLinkSuppressed = $duplicateFreeRowPreserved
    && $links === []
    && $annotations === []
    && !str_contains($encodedReview, 'stale.example.com')
    && !str_contains($encodedReview, 'Stale duplicate free annotation import');

if (!$currentTextImported || !$staleLinkSuppressed) {
    throw new RuntimeException('Expected current duplicate free xref-stream row to suppress stale annotation link import.');
}

echo '<!-- markerpdf-xref-prev-chain-free-annotation-duplicate-row-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-prev-chain-free-map',
    'support_component' => 'native-pdf-xref-stream-free-row-boundary',
    'native_boundary' => 'latest xref-stream duplicate rows keep the first current free annotation row before stale in-use duplicates',
    'paragraphs' => $lines,
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'duplicate_index_range_present' => str_contains($pdf, '/Index [3 2 7 1 7 1 20 1]'),
    'duplicate_free_row_preserved' => $duplicateFreeRowPreserved,
    'stale_link_suppressed' => $staleLinkSuppressed,
    'stale_payload_in_visible_text' => str_contains($plainText, 'Stale duplicate free annotation import'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
