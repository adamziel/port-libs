<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous Comment Trailer Free Map Import) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current Comment Trailer Free Map Import) Tj ET';
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 410 718] /Contents (Stale comment trailer free annotation) /A << /S /URI /URI (https://stale.example.com/comment-trailer-free-map) >> >>');

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
    . "% trailer << /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "7 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n999999\n%%EOF";

$textExtractor = new PdfTextExtractor();
$linkExtractor = new PdfLinkAnnotationExtractor();
$annotationExtractor = new PdfAnnotationExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$annotations = $annotationExtractor->extractPageAnnotations($pdf);
$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 410.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 410.0, 718.0],
            'spans' => [[
                'text' => 'Current Comment Trailer Free Map Import',
                'bbox' => [72.0, 700.0, 410.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

$commentedTrailerSkipped = isset($freeObjects[7])
    && $links === []
    && $annotations === []
    && !isset($span['link_uri'])
    && !isset($span['link_annotation_object']);

if ($lines !== ['Current Comment Trailer Free Map Import'] || !$commentedTrailerSkipped) {
    throw new RuntimeException('Expected commented trailer tokens to be ignored while preserving current free xref rows.');
}

echo '<!-- markerpdf-classic-xref-free-map-comment-trailer-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-classic-xref-free-object-map',
    'support_component' => 'native-pdf-token-boundary-classic-xref-rebuild',
    'commented_trailer_token_skipped' => true,
    'current_free_row_preserved' => isset($freeObjects[7]),
    'stale_link_annotation_suppressed' => $links === [] && $annotations === [],
    'span_link_promoted' => isset($span['link_uri']),
    'paragraphs' => $lines,
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
