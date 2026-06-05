<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 60 210 Td (Visible docs Margin decoy Edge target Outside decoy Quad visible Quad outside) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [40 200 134 218] /Contents (Partially cropped URI link review) /A << /S /URI /URI (https://example.com/visible-crop-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 200 230 218] /Dest [3 0 R /FitH 220] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 200 310 218] /Contents (Outside crop link review) /A << /S /URI /URI (https://example.com/outside-crop-link) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 230 300 248] /QuadPoints [60 248 130 248 60 238 130 238 260 240 300 240 260 230 300 230] /A << /S /URI /URI (https://example.com/visible-quad-link) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [40.0, 200.0, 310.0, 248.0],
        'lines' => [[
            'bbox' => [40.0, 200.0, 310.0, 248.0],
            'spans' => [
                ['text' => 'Visible docs', 'bbox' => [50.0, 200.0, 134.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Margin decoy', 'bbox' => [40.0, 200.0, 48.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Edge target', 'bbox' => [150.0, 200.0, 230.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Outside decoy', 'bbox' => [260.0, 200.0, 310.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Quad visible', 'bbox' => [60.0, 238.0, 130.0, 248.0], 'font' => 'Helvetica'],
                ['text' => ' Quad outside', 'bbox' => [260.0, 230.0, 300.0, 240.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

echo '<!-- markerpdf-pdf-link-crop-boundary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'native_boundary' => 'PDF link annotation rectangles and QuadPoints are clipped to the effective page CropBox before WordPress span promotion',
    'promoted_link_count' => count($links),
    'promoted_annotation_objects' => array_column($links, 'annotation_object'),
    'partial_rect_clipped_to_page' => $links[0]['rect_clipped_to_page'] ?? null,
    'partial_visible_rect' => $links[0]['visible_rect'] ?? null,
    'outside_link_excluded' => !str_contains(json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '', 'outside-crop-link'),
    'margin_decoy_linked' => isset($spans[1]['link_uri']),
    'destination_page' => $spans[2]['link_destination_page'] ?? null,
    'quad_rects_excluded_by_page_bbox' => $links[2]['quad_rects_excluded_by_page_bbox'] ?? null,
    'quad_outside_span_linked' => isset($spans[5]['link_uri']),
    'visible_text_excludes_link_review_payloads' => !str_contains($blocks[0]['text'] ?? '', 'link review') && !str_contains($blocks[0]['text'] ?? '', 'outside-crop-link'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '">' . $text . '</a>';
        continue;
    }

    if (isset($span['link_destination_page'])) {
        echo '<span data-markerpdf-link-destination-page="' . (int) $span['link_destination_page'] . '" data-markerpdf-link-view-mode="' . htmlspecialchars((string) ($span['link_view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</span>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
