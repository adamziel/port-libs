<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseContent = 'BT /F1 12 Tf 72 720 Td (Base damaged Prev annotation import) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged Prev annotation import) Tj ET';

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
$basePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$baseContentOffset = $addObject(4, 0, "<< /Length " . strlen($baseContent) . " >>\nstream\n{$baseContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale damaged Prev link import) /A << /S /URI /URI (https://stale.example.com/damaged-prev-link-import) >> >>');

$baseXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($catalogOffset)
    . $xrefTableRow($pagesOffset)
    . $xrefTableRow($basePageOffset)
    . $xrefTableRow($baseContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleAnnotationOffset)
    . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
    . "startxref\n{$baseXrefOffset}\n%%EOF\n";

$middleXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "7 1\n"
    . $xrefTableRow(0, 1, 'f')
    . "trailer\n<< /Size 8 /Prev {$baseXrefOffset} >>\n"
    . "startxref\n{$middleXrefOffset}\n%%EOF\n";

$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$currentRows = ''
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $currentXrefOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress damaged-Prev free annotation smoke xref stream.');
}

$damagedPrevOffset = $middleXrefOffset + 5;
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $damagedPrevOffset . ' /Index [3 2 20 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$encodedReview = json_encode([$freeObjects, $links, $annotations], JSON_UNESCAPED_SLASHES) ?: '';

$currentTextImported = $lines === ['Current damaged Prev annotation import'];
$staleLinkSuppressed = isset($freeObjects[7])
    && $links === []
    && $annotations === []
    && !str_contains($encodedReview, 'stale.example.com')
    && !str_contains($encodedReview, 'Stale damaged Prev link import');

if (!$currentTextImported || !$staleLinkSuppressed) {
    throw new RuntimeException('Expected damaged xref-stream /Prev repair to suppress the stale freed annotation before WordPress import.');
}

echo '<!-- markerpdf-xref-prev-chain-free-annotation-damaged-prev-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-prev-chain-free-map',
    'support_component' => 'native-pdf-xref-prev-chain-boundary',
    'native_boundary' => 'damaged xref-stream /Prev offsets are repaired to the previous real xref section before stale annotation link promotion',
    'paragraphs' => $lines,
    'base_xref_offset' => $baseXrefOffset,
    'middle_xref_offset' => $middleXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'damaged_prev_offset' => $damagedPrevOffset,
    'free_annotation_object_detected' => isset($freeObjects[7]),
    'stale_link_suppressed' => $staleLinkSuppressed,
    'stale_payload_in_visible_text' => str_contains($plainText, 'Stale damaged Prev link import'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
