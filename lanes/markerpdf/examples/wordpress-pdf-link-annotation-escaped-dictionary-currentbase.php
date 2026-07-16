<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Escaped link Hidden stale) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [8 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Sub#74ype /Link /Re#63t [72 700 158 718] /#41 << /S /URI /URI (https://example.com/escaped-annotation-keys) >> /#46 4 >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Sub#74ype /Link /Re#63t [168 700 250 718] /#41 << /S /URI /URI (https://example.com/private-hidden-stale) >> /#46 2 >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 250.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'spans' => [
                ['text' => 'Escaped link', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden stale', 'bbox' => [168.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedPages = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';

echo '<!-- markerpdf-pdf-link-annotation-escaped-dictionary-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'Escaped top-level Link annotation dictionary names are decoded before WordPress span promotion',
    'link_annotation_object' => $links[0]['links'][0]['annotation_object'] ?? null,
    'link_uri' => $spans[0]['link_uri'] ?? null,
    'hidden_escaped_flag_excluded' => !isset($spans[1]['link_uri']) && !str_contains($encodedPages, 'private-hidden-stale'),
    'escaped_subtype_resolved' => ($links[0]['links'][0]['annotation_subtype'] ?? null) === 'Link',
    'escaped_rect_resolved' => ($spans[0]['link_rect'] ?? null) === [72.0, 700.0, 158.0, 718.0],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    $html = htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace('/\[([^]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html) ?? $html;
    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$html}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
