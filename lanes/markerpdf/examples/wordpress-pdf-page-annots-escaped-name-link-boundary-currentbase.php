<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Escaped docs Escaped highlight Stale private) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /PieceInfo << /WPImport << /Private << /Annots [8 0 R] /ReviewStage /stale-private-link >> >> >> /Ann#6fts [7 0 R 9 0 R 10 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Escaped docs link review) /A << /S /URI /URI (https://example.com/escaped-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 350 718] /Contents (Private escaped stale link) /A << /S /URI /URI (https://example.com/private-escaped-stale) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Escaped highlight review) /T (Import QA) /C [0.6 0.8 1] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Text /Rect [360 700 420 718] /Contents (Escaped sticky note) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 350.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'spans' => [
                ['text' => 'Escaped docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Escaped highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale private', 'bbox' => [270.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
$encodedReview = json_encode($reviewPages, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-pdf-page-annots-escaped-name-link-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'PDF page-level escaped /Ann#6fts is decoded as /Annots before WordPress link and markup import',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'link_uri' => $spans[0]['link_uri'] ?? null,
    'markup_review' => $spans[1]['review_annotations'][0]['contents'] ?? null,
    'private_nested_annots_promoted' => str_contains($encodedReview, 'private-escaped-stale'),
    'stale_span_linked' => isset($spans[2]['link_uri']),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        echo '<a href="' . htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</a>';
        continue;
    }

    $reviews = $span['review_annotations'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        echo '<mark data-markerpdf-review="' . htmlspecialchars((string) ($reviews[0]['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</mark>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
