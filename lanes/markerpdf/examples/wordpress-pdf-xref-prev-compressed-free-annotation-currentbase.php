<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseContent = 'BT /F1 12 Tf 72 720 Td (Base compressed Prev annotation page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current compressed Prev annotation page) Tj ET';

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
$objectStream = static function (array $members): array {
    $headerPairs = [];
    $memberIndexes = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $plain = $header . "\n" . $objectData;
    $compressed = gzcompress($plain);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress xref free-map Prev helper object stream.');
    }

    return [
        'first' => strlen($header) + 1,
        'indexes' => $memberIndexes,
        'content' => $compressed,
        'count' => count($members),
    ];
};

$catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$basePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$baseContentOffset = $addObject(4, 0, "<< /Length " . strlen($baseContent) . " >>\nstream\n{$baseContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale compressed Prev free annotation) /A << /S /URI /URI (https://stale.example.com/compressed-prev-free-annotation) >> >>');

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
$prevHelperStream = $objectStream([30 => (string) $middleXrefOffset]);
$prevHelperCarrierOffset = $addObject(90, 0, '<< /Type /ObjStm /N ' . $prevHelperStream['count'] . ' /First ' . $prevHelperStream['first'] . ' /Filter /FlateDecode /Length ' . strlen($prevHelperStream['content']) . " >>\nstream\n{$prevHelperStream['content']}\nendstream");

$currentXrefOffset = strlen($pdf);
$currentRows = ''
    . $xrefStreamRow(1, $currentPageOffset, 0)
    . $xrefStreamRow(1, $currentContentOffset, 0)
    . $xrefStreamRow(1, $currentXrefOffset, 0)
    . $xrefStreamRow(2, 90, $prevHelperStream['indexes'][30])
    . $xrefStreamRow(1, $prevHelperCarrierOffset, 0);
$compressedRows = gzcompress($currentRows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress compressed-Prev free annotation xref stream.');
}

$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /Prev 30 0 R /Index [3 2 20 1 30 1 90 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "30 0 obj\n999999\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 330.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'spans' => [[
                'text' => 'Current compressed Prev annotation page',
                'bbox' => [72.0, 700.0, 330.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];
$linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
$encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$freeMapFollowsCompressedPrev = isset($freeObjects[7]);
$staleAnnotationExcluded = $links === []
    && $annotations === []
    && !isset($span['link_uri'])
    && !isset($span['link_annotation_object'])
    && !str_contains($encodedReview, 'stale.example.com')
    && !str_contains($encodedReview, 'Stale compressed Prev free annotation');

if (!$freeMapFollowsCompressedPrev || !$staleAnnotationExcluded) {
    throw new RuntimeException('Expected compressed /Prev helper to keep stale freed annotations out of WordPress links.');
}

echo '<!-- markerpdf-xref-prev-compressed-free-annotation-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-prev-free-object-map',
    'native_boundary' => 'xref-stream /Prev numeric helper stored in an object stream is followed before WordPress link promotion',
    'free_map_follows_compressed_prev_helper' => $freeMapFollowsCompressedPrev,
    'stale_annotation_link_excluded' => $links === [],
    'stale_annotation_review_excluded' => $annotations === [],
    'stale_span_not_promoted' => !isset($span['link_uri']) && !isset($span['link_annotation_object']),
    'post_xref_direct_prev_decoy_ignored' => str_contains($pdf, "30 0 obj\n999999\nendobj"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) $span['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
