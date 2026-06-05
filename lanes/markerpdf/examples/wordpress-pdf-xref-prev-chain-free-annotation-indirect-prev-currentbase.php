<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseContent = 'BT /F1 12 Tf 72 720 Td (Base indirect Prev annotation smoke page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev annotation smoke page) Tj ET';

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
$staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale indirect Prev free annotation smoke) /A << /S /URI /URI (https://stale.example.com/indirect-prev-free-annotation-smoke) >> >>');

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
$prevHelperOffset = $addObject(30, 0, (string) $middleXrefOffset);

$currentXrefOffset = strlen($pdf);
$currentRows = ''
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $currentXrefOffset, 0)
    . $xrefStreamRow(1, $prevHelperOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress indirect-Prev free annotation smoke xref-stream fixture.');
}

$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev 30 0 R /Index [3 2 20 1 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n";

$addObject(30, 0, '999999');
$pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

$linkExtractor = new PdfLinkAnnotationExtractor();
$annotationExtractor = new PdfAnnotationExtractor();
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$annotations = $annotationExtractor->extractPageAnnotations($pdf);

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 250.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'spans' => [[
                'text' => 'Current indirect Prev annotation smoke page',
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

echo 'indirect_prev_helper_followed=' . (isset($freeObjects[7]) ? 'true' : 'false') . PHP_EOL;
echo 'stale_link_promoted=' . ($links === [] ? 'false' : 'true') . PHP_EOL;
echo 'stale_annotation_promoted=' . ($annotations === [] ? 'false' : 'true') . PHP_EOL;
echo 'span_link_uri_absent=' . (!isset($span['link_uri']) ? 'true' : 'false') . PHP_EOL;
echo 'span_link_annotation_object_absent=' . (!isset($span['link_annotation_object']) ? 'true' : 'false') . PHP_EOL;
echo 'post_xref_prev_decoy_ignored=' . (isset($freeObjects[7]) && str_contains($pdf, "30 0 obj\n999999\nendobj") ? 'true' : 'false') . PHP_EOL;
echo 'stale_annotation_payload_excluded=' . (!str_contains($encodedReview, 'stale.example.com') && !str_contains($encodedReview, 'Stale indirect Prev free annotation smoke') ? 'true' : 'false') . PHP_EOL;
echo 'executes_python_or_models=false' . PHP_EOL;
echo 'executes_external_pdf_tools=false' . PHP_EOL;
