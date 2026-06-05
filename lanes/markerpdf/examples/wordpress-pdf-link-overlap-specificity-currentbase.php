<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Global banner Focused docs Sidebar note) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 690 365 730] /Contents (Broad banner review) /A << /S /URI /URI (https://example.com/broad-banner) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Focused docs review) /A << /S /URI /URI (https://example.com/focused-docs) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 690 360 730] /QuadPoints [270 718 360 718 270 700 360 700] /Contents (Sidebar quad review) /A << /S /URI /URI (https://example.com/sidebar-note) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Global banner', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Focused docs', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Sidebar note', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = (string) ($blocks[0]['text'] ?? '');

$summary = [
    'support_component' => 'native-pdf-link-overlap-specificity-boundary',
    'native_boundary' => 'overlapping Link annotations are all preserved for review, but WordPress span promotion chooses the best-matching narrow Rect or QuadPoints candidate instead of the first broad annotation',
    'annotation_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_span_objects' => array_map(
        static fn (array $span): ?int => is_int($span['link_annotation_object'] ?? null) ? $span['link_annotation_object'] : null,
        $spans
    ),
    'promoted_span_uris' => array_map(
        static fn (array $span): ?string => is_string($span['link_uri'] ?? null) ? $span['link_uri'] : null,
        $spans
    ),
    'focused_docs_not_swallowed_by_broad_link' => ($spans[1]['link_uri'] ?? null) === 'https://example.com/focused-docs',
    'sidebar_quad_not_swallowed_by_broad_link' => ($spans[2]['link_uri'] ?? null) === 'https://example.com/sidebar-note',
    'sidebar_quad_index' => $spans[2]['link_quad_index'] ?? null,
    'visible_text_imported' => str_contains($visibleText, 'Global banner Focused docs Sidebar note'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Broad banner review')
        || str_contains($visibleText, 'Focused docs review')
        || str_contains($visibleText, 'Sidebar quad review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $wordpressText !== '[Global banner](https://example.com/broad-banner) [Focused docs](https://example.com/focused-docs) [Sidebar note](https://example.com/sidebar-note)'
    || $summary['annotation_objects'] !== [7, 8, 9]
    || $summary['promoted_span_objects'] !== [7, 8, 9]
    || $summary['focused_docs_not_swallowed_by_broad_link'] !== true
    || $summary['sidebar_quad_not_swallowed_by_broad_link'] !== true
    || $summary['annotation_payload_text_visible'] !== false
) {
    throw new RuntimeException('Unexpected markerPDF overlapping link specificity smoke output.');
}

echo '<!-- markerpdf-pdf-link-overlap-specificity-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
