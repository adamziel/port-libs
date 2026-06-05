<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 210 Td (Quad rescue Rect decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 200 310 218] /QuadPoints [72 218 150 218 72 200 150 200] /Contents (Stale rect quad review) /A << /S /URI /URI (https://example.com/quad-rescue) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 200.0, 310.0, 218.0],
        'lines' => [[
            'bbox' => [72.0, 200.0, 310.0, 218.0],
            'spans' => [
                ['text' => 'Quad rescue', 'bbox' => [72.0, 200.0, 150.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Rect decoy', 'bbox' => [260.0, 200.0, 310.0, 218.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$summary = [
    'support_component' => 'native-pdf-link-quadpoints-boundary',
    'native_boundary' => 'PDF Link annotation visible QuadPoints can constrain WordPress span promotion when /Rect is stale or off-page',
    'promoted_link_count' => count($links),
    'promoted_annotation_objects' => array_column($links, 'annotation_object'),
    'stale_rect_clipped_to_page' => $links[0]['rect_clipped_to_page'] ?? null,
    'visible_quad_rects' => $links[0]['visible_quad_rects'] ?? [],
    'quad_rescue_linked' => isset($spans[0]['link_uri']),
    'rect_decoy_linked' => isset($spans[1]['link_uri']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Quad rescue Rect decoy'),
    'visible_text_excludes_link_review_payloads' => !str_contains($visibleText, 'quad-rescue')
        && !str_contains($visibleText, 'Stale rect quad review'),
    'review_metadata_kept_out_of_visible_text' => !str_contains($blocks[0]['text'] ?? '', 'Stale rect quad review')
        && !str_contains($blocks[0]['text'] ?? '', 'link review'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Stale rect quad review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($summary['promoted_annotation_objects'] ?? []) !== [7]
    || ($summary['quad_rescue_linked'] ?? null) !== true
    || ($summary['rect_decoy_linked'] ?? null) !== false
    || ($summary['visible_text_imported'] ?? null) !== true
    || ($summary['visible_text_excludes_link_review_payloads'] ?? null) !== true
) {
    throw new RuntimeException('Unexpected markerPDF stale-Rect QuadPoints boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-quadpoints-stale-rect-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $quadIndex = (int) ($span['link_quad_index'] ?? -1);
        echo '<a href="' . $href . '" data-markerpdf-link-quad="' . $quadIndex . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
