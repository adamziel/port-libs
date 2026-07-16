<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous header-garbage free-map page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current header-garbage free-map page) Tj ET';

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

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 275 718] /Contents (Stale header-garbage free annotation) /A << /S /URI /URI (https://stale.example.com/header-garbage-free-map) >> >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0:0'])
    . $xrefRow($offsets['2:0:1'])
    . $xrefRow($offsets['3:0:2'])
    . $xrefRow($offsets['4:0:3'])
    . $xrefRow($offsets['5:0:4'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['7:0:5'])
    . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 2\n"
    . $xrefRow($currentPageOffset)
    . $xrefRow($currentContentOffset)
    . "7 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

$headerGarbageXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "not a free-map subsection header before later-looking rows\n"
    . "7 1\n"
    . $xrefRow($offsets['7:0:5'])
    . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$textExtractor = new PdfTextExtractor();
$linkExtractor = new PdfLinkAnnotationExtractor();
$annotationExtractor = new PdfAnnotationExtractor();
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$annotations = $annotationExtractor->extractPageAnnotations($pdf);
$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 275.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 275.0, 718.0],
            'spans' => [[
                'text' => 'Current header-garbage free-map page',
                'bbox' => [72.0, 700.0, 275.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
$encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$textLines = $textExtractor->extractTextLines($pdf);

$smoke = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-free-object-map-header-garbage-currentbase',
    'native_boundary' => 'free-object classic xref rebuild rejects malformed-leading decoy tables before annotation promotion',
    'damaged_startxref_operand' => 999999,
    'current_xref_after_previous' => $currentXrefOffset > $previousXrefOffset,
    'header_garbage_xref_after_current' => $headerGarbageXrefOffset > $currentXrefOffset,
    'current_text_selected' => $textLines === ['Current header-garbage free-map page'],
    'free_annotation_preserved_from_current_xref' => isset($freeObjects[7]),
    'suppresses_stale_link_annotation' => $links === [] && !isset($span['link_uri']),
    'suppresses_stale_review_annotation' => $annotations === [] && !isset($span['link_annotation_object']),
    'excludes_stale_annotation_uri' => !str_contains($encodedReview, 'stale.example.com/header-garbage-free-map'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$smoke['current_text_selected']
    || !$smoke['free_annotation_preserved_from_current_xref']
    || !$smoke['suppresses_stale_link_annotation']
    || !$smoke['suppresses_stale_review_annotation']
    || !$smoke['excludes_stale_annotation_uri']
) {
    throw new RuntimeException('Expected malformed-leading classic xref decoy to stay out of free annotation review.');
}

echo '<!-- markerpdf-xref-classic-free-map-header-garbage-smoke ' . htmlspecialchars(
    json_encode($smoke, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>Current header-garbage free-map page</p>' . "\n";
echo "<!-- /wp:paragraph -->\n";
