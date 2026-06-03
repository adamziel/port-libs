<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [10 20 210 320] /CropBox [20 40 180 240] /Rotate 90 /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 9 0 R /Annots [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [30 150 110 170] /A << /S /URI /URI (https://example.com/rotated-link) >> >>\nendobj\n"
    . "9 0 obj\n2\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 400.0, 320.0],
    'rotation' => 90,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [220.0, 20.0, 260.0, 180.0],
        'lines' => [[
            'bbox' => [220.0, 20.0, 260.0, 180.0],
            'spans' => [
                ['text' => 'Rotated link', 'bbox' => [220.0, 20.0, 260.0, 180.0], 'font' => 'Helvetica'],
                ['text' => ' raw decoy', 'bbox' => [30.0, 150.0, 110.0, 170.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

if (($spans[0]['link_uri'] ?? null) !== 'https://example.com/rotated-link'
    || ($spans[0]['link_rect_coordinate_space'] ?? null) !== 'marker_pdftext_display'
    || isset($spans[1]['link_uri'])
    || ($blocks[0]['text'] ?? null) !== '[Rotated link](https://example.com/rotated-link) raw decoy'
) {
    throw new RuntimeException('Expected rotated/UserUnit link annotation to attach only to the pdftext display-space WordPress span.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-page-geometry',
    'native_boundary' => 'PDF Link annotation /Rect transformed through inherited /CropBox, /Rotate, and page-local /UserUnit before marker/pdftext span promotion',
    'link_count' => count($links),
    'uri' => $spans[0]['link_uri'] ?? null,
    'link_rect_coordinate_space' => $spans[0]['link_rect_coordinate_space'] ?? null,
    'page_rect' => $spans[0]['link_page_rect'] ?? null,
    'pdftext_rect' => $spans[0]['link_pdftext_rect'] ?? null,
    'raw_decoy_linked' => isset($spans[1]['link_uri']),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-rotation-userunit-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $space = htmlspecialchars((string) ($span['link_rect_coordinate_space'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-link-space="' . $space . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
