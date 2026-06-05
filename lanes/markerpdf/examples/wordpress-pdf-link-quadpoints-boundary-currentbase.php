<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Left link middle Right link) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 680 318 718] /QuadPoints [72 718 150 718 72 700 150 700 230 698 318 698 230 680 318 680] /A << /S /URI /URI (https://example.com/quad-link) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 680.0, 318.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 680.0, 318.0, 718.0],
            'spans' => [
                ['text' => 'Left link', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' middle', 'bbox' => [160.0, 690.0, 220.0, 708.0], 'font' => 'Helvetica'],
                ['text' => ' Right link', 'bbox' => [230.0, 680.0, 318.0, 698.0], 'font' => 'Helvetica'],
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
    'native_boundary' => 'PDF Link annotation /QuadPoints constrain clickable span areas before /Rect fallback during WordPress import',
    'link_count' => count($links),
    'quad_rect_count' => count($links[0]['quad_rects'] ?? []),
    'link_rect' => $links[0]['rect'] ?? null,
    'quad_rects' => $links[0]['quad_rects'] ?? [],
    'first_span_quad_index' => $spans[0]['link_quad_index'] ?? null,
    'middle_span_linked' => isset($spans[1]['link_uri']),
    'second_span_quad_index' => $spans[2]['link_quad_index'] ?? null,
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Left link middle Right link'),
    'visible_text_excludes_uri_payload' => !str_contains($visibleText, 'quad-link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-quadpoints-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

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
