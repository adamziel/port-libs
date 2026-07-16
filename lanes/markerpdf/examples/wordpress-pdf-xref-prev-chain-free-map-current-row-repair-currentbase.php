<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous free-map repair page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current free-map repair page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 724] /Contents (Previous freed link) /A << /S /URI /URI (https://stale.example.com/free-map-repair) >> >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($catalogOffset)
    . $xrefRow($pagesOffset)
    . $xrefRow($previousPageOffset)
    . $xrefRow($previousContentOffset)
    . $xrefRow($fontOffset)
    . $xrefRow(0, 0, 'f')
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [72 700 260 724] /Contents (Current repaired link) /A << /S /URI /URI (https://current.example.com/free-map-repair) >> >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 2\n"
    . $xrefRow($currentPageOffset)
    . $xrefRow($currentContentOffset)
    . "30 1\n"
    . $xrefRow($currentAnnotationOffset)
    . "trailer\n<< /Size 31 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 260.0, 724.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 260.0, 724.0],
            'spans' => [[
                'text' => 'Current free-map repair page',
                'bbox' => [72.0, 700.0, 260.0, 724.0],
            ]],
        ]],
    ]],
]];
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];
$encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$currentLinkPromoted = ($span['link_uri'] ?? null) === 'https://current.example.com/free-map-repair'
    && ($span['link_annotation_object'] ?? null) === 7;
$currentAnnotationVisible = ($annotations[0]['annotations'][0]['annotation_object'] ?? null) === 7
    && ($annotations[0]['annotations'][0]['contents'] ?? null) === 'Current repaired link';
$freeMapRepaired = !isset($freeObjects[7]) && isset($freeObjects[6]);
$staleReviewExcluded = !str_contains($encodedReview, 'stale.example.com')
    && !str_contains($encodedReview, 'Previous freed link');

if ($lines !== ['Current free-map repair page'] || !$freeMapRepaired || !$currentLinkPromoted || !$currentAnnotationVisible || !$staleReviewExcluded) {
    throw new RuntimeException('Expected current malformed xref row owner to restore object 7 before inherited free rows suppress WordPress link review.');
}

echo '<!-- markerpdf-xref-prev-chain-free-map-current-row-repair-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-xref-prev-chain-free-map',
    'support_component' => 'native-pdf-xref-prev-free-object-map',
    'native_boundary' => 'latest Prev-chain in-use rows are repaired by current direct-object offset owner before inherited free rows suppress annotation review',
    'paragraphs' => $lines,
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'malformed_current_row_object' => 30,
    'current_annotation_object' => 7,
    'free_map_repaired' => $freeMapRepaired,
    'current_link_promoted' => $currentLinkPromoted,
    'current_annotation_visible' => $currentAnnotationVisible,
    'stale_review_excluded' => $staleReviewExcluded,
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p><a href="https://current.example.com/free-map-repair">' . htmlspecialchars($lines[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</a></p>\n";
echo "<!-- /wp:paragraph -->\n";
