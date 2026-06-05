<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Broken quad Valid quad Rect decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 345 718] /QuadPoints [72 718 150 718 72 700 150 /BadCoordinate 218 718 300 718 218 700 300 700] /Contents (Malformed quad link review) /A << /S /URI /URI (https://example.com/valid-quad-only) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 345.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 345.0, 718.0],
            'spans' => [
                ['text' => 'Broken quad', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Valid quad', 'bbox' => [218.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Rect decoy', 'bbox' => [310.0, 700.0, 345.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$markdown = (string) ($blocks[0]['text'] ?? '');

if ($markdown !== 'Broken quad [Valid quad](https://example.com/valid-quad-only) Rect decoy') {
    throw new RuntimeException('Unexpected WordPress Markdown for malformed QuadPoints boundary.');
}
if (isset($spans[0]['link_uri']) || isset($spans[2]['link_uri'])) {
    throw new RuntimeException('Malformed quad group or broad Rect decoy was promoted.');
}
if (($spans[1]['link_uri'] ?? null) !== 'https://example.com/valid-quad-only') {
    throw new RuntimeException('Valid later QuadPoints group was not promoted.');
}
if (str_contains($plainText, 'valid-quad-only') || str_contains($plainText, 'Malformed quad link review')) {
    throw new RuntimeException('Link annotation payload leaked into visible text.');
}

$summary = [
    'support_component' => 'native-pdf-link-malformed-quadpoints-boundary',
    'native_boundary' => 'malformed Link annotation QuadPoints groups are skipped without recombining later coordinates before WordPress import',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'quad_rects' => $links[0]['links'][0]['quad_rects'] ?? [],
    'malformed_first_quad_linked' => isset($spans[0]['link_uri']),
    'valid_later_quad_linked' => isset($spans[1]['link_uri']),
    'rect_decoy_linked' => isset($spans[2]['link_uri']),
    'wordpress_markdown' => $markdown,
    'visible_text_imported' => str_contains($plainText, 'Broken quad Valid quad Rect decoy'),
    'annotation_payload_text_visible' => str_contains($plainText, 'valid-quad-only') || str_contains($plainText, 'Malformed quad link review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-malformed-quadpoints-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>';
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-link-quad="' . (int) ($span['link_quad_index'] ?? -1) . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n";
echo "<!-- /wp:paragraph -->\n";
