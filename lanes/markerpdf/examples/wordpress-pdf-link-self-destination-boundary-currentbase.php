<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Self fit Self xyz Other page Direct docs) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Other page destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 140 718] /Contents (Self page Fit review) /Dest [3 0 R /Fit] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [150 700 222 718] /Contents (Self page XYZ review) /Dest [3 0 R /XYZ 72 720 0] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [232 700 310 718] /Contents (Other page Fit review) /Dest [4 0 R /Fit] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [320 700 400 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs) >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 400.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 400.0, 718.0],
            'spans' => [
                ['text' => 'Self fit', 'bbox' => [72.0, 700.0, 140.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Self xyz', 'bbox' => [150.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Other page', 'bbox' => [232.0, 700.0, 310.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct docs', 'bbox' => [320.0, 700.0, 400.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
$encodedFirstSpan = json_encode($spans[0] ?? [], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-self-destination-boundary',
    'native_boundary' => 'same-page positionless /Fit Link destinations remain page-level review metadata and are not promoted to WordPress text spans',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'page_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'self_fit_page_link_reviewed' => str_contains($encodedLinks, 'Self page Fit review'),
    'self_fit_span_promoted' => isset($spans[0]['link_destination_page']) || isset($spans[0]['link_actions_review']),
    'self_fit_span_review_payload' => str_contains($encodedFirstSpan, 'Self page Fit review'),
    'self_xyz_span_promoted' => ($spans[1]['link_destination_page'] ?? null) === 0
        && ($spans[1]['link_view_mode'] ?? null) === 'XYZ',
    'other_page_span_promoted' => ($spans[2]['link_destination_page'] ?? null) === 1
        && ($spans[2]['link_view_mode'] ?? null) === 'Fit',
    'uri_span_promoted' => ($spans[3]['link_uri'] ?? null) === 'https://example.com/direct-docs',
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => str_contains($visibleText, 'Self fit Self xyz Other page Direct docs')
        && str_contains($visibleText, 'Other page destination body'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Self page Fit review')
        || str_contains($visibleText, 'Self page XYZ review')
        || str_contains($visibleText, 'Other page Fit review')
        || str_contains($visibleText, 'Direct docs review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($summary['self_fit_span_promoted'] || $summary['self_fit_span_review_payload']) {
    throw new RuntimeException('Same-page positionless /Fit destination should not be promoted onto the WordPress span.');
}
if (!$summary['self_fit_page_link_reviewed'] || !$summary['self_xyz_span_promoted'] || !$summary['other_page_span_promoted'] || !$summary['uri_span_promoted']) {
    throw new RuntimeException('Expected review rows, explicit-position local links, remote page links, and URI links to be preserved.');
}
if ($wordpressText !== 'Self fit Self xyz Other page [Direct docs](https://example.com/direct-docs)') {
    throw new RuntimeException('Unexpected WordPress Markdown link rendering.');
}
if ($summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Annotation review payload text leaked into visible WordPress content.');
}

echo '<!-- markerpdf-pdf-link-self-destination-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
