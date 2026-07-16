<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous early-endstream free-map page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current early-endstream free-map page) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . count($offsets)] = $offset;
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$previousCatalogOffset = $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$previousPagesOffset = $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$previousFontOffset = $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleAnnotationOffset = $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 280 718] /Contents (Stale early-endstream free annotation) /A << /S /URI /URI (https://stale.example.com/early-endstream-free-map) >> >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($previousCatalogOffset)
    . $xrefRow($previousPagesOffset)
    . $xrefRow($previousPageOffset)
    . $xrefRow($previousContentOffset)
    . $xrefRow($previousFontOffset)
    . $xrefRow(0, 0, 'f')
    . $xrefRow($staleAnnotationOffset)
    . "trailer\n<< /Size 80 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentPageOffset = $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 2\n"
    . $xrefRow($currentPageOffset)
    . $xrefRow($currentContentOffset)
    . "7 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 80 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

$fakePayload = "WordPress import note before an early marker\n"
    . "endstream\n"
    . "endobj\n"
    . "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($previousCatalogOffset)
    . $xrefRow($previousPagesOffset)
    . $xrefRow($previousPageOffset)
    . $xrefRow($previousContentOffset)
    . $xrefRow($previousFontOffset)
    . $xrefRow(0, 0, 'f')
    . $xrefRow($staleAnnotationOffset)
    . "trailer\n<< /Size 80 /Root 1 0 R >>\n"
    . "payload still belongs to object 60 after fake endstream\n";
$pdf .= "60 0 obj\n"
    . "<< /Length " . strlen($fakePayload) . " >>\n"
    . "stream\n"
    . $fakePayload
    . "endstream\n"
    . "endobj\n"
    . "startxref\n999999\n%%EOF";

$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$linkedPages = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (($freeObjects[7] ?? null) !== true) {
    throw new RuntimeException('Expected current xref free row for annotation object 7 to survive classic rebuild.');
}
if ($linkedPages !== [] || str_contains($encodedReview, 'stale.example.com/early-endstream-free-map')) {
    throw new RuntimeException('Stale link annotation leaked through an early endstream xref decoy.');
}
if ($visibleText !== 'Current early-endstream free-map page') {
    throw new RuntimeException('Expected current page text after classic xref rebuild.');
}

$summary = [
    'support_component' => 'native-pdf-xref-classic-rebuild-early-endstream-free-map-currentbase',
    'native_boundary' => 'declared-length stream ownership hides fake endstream/endobj/xref bytes from free-object-map rebuild',
    'damaged_startxref_rebuilt' => str_contains($pdf, "startxref\n999999"),
    'current_xref_frees_annotation_object' => ($freeObjects[7] ?? null) === true,
    'early_endstream_decoy_present' => str_contains($pdf, "endstream\nendobj\nxref\n0 8\n"),
    'stale_link_promoted' => str_contains($encodedReview, 'stale.example.com/early-endstream-free-map'),
    'link_pages' => count($linkedPages),
    'visible_text_imported' => $visibleText === 'Current early-endstream free-map page',
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-xref-classic-rebuild-early-endstream-free-map-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($visibleText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
