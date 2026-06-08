<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Clean docs Tailed action object Tailed destination object) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 40 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean docs review) /A << /S /URI /URI (https://example.com/clean-docs-indirect-tail-boundary) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 292 718] /Contents (Tailed action object review) /A 20 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [302 700 465 718] /Contents (Tailed destination object review) /Dest 30 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.com/tailed-action-object-promote) >> 21 0 R\nendobj\n"
    . "21 0 obj\n<< /S /JavaScript /JS (tailActionObjectReview\\(\\)) >>\nendobj\n"
    . "30 0 obj\n[4 0 R /XYZ 36 700 null] 22 0 R\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (https://example.com/tailed-destination-object-promote) >>\nendobj\n"
    . "40 0 obj\n<< /Names [(safe-target) [4 0 R /FitH 700]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 465.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 465.0, 718.0],
            'spans' => [
                ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed action object', 'bbox' => [160.0, 700.0, 292.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed destination object', 'bbox' => [302.0, 700.0, 465.0, 718.0], 'font' => 'Helvetica'],
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

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$promotedObjects = array_column($links[0]['links'] ?? [], 'annotation_object');
$spanRows = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

if ($wordpressText !== '[Clean docs](https://example.com/clean-docs-indirect-tail-boundary) Tailed action object Tailed destination object') {
    throw new RuntimeException('Expected only the clean direct action to become a WordPress Markdown link.');
}
if ($promotedObjects !== [7]) {
    throw new RuntimeException('Expected tailed indirect action/destination annotations to stay unpromoted.');
}
if (isset($spanRows[1]['link_uri']) || isset($spanRows[2]['link_destination_page'])) {
    throw new RuntimeException('Tailed indirect action/destination objects must not attach link metadata to spans.');
}
if (str_contains($encodedReview, 'tailed-action-object-promote') || str_contains($encodedReview, 'tailed-destination-object-promote')) {
    throw new RuntimeException('Tailed indirect object payloads must stay out of review metadata.');
}
if (str_contains($visibleText, 'tailed-action-object-promote') || str_contains($visibleText, 'Tailed action object review')) {
    throw new RuntimeException('Annotation payload text leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-indirect-object-tail-boundary',
    'native_boundary' => 'Indirect Link /A and /Dest objects with trailing top-level operands are fail-closed before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => $promotedObjects,
    'wordpress_text' => $wordpressText,
    'tailed_action_object_rejected' => !isset($spanRows[1]['link_uri']),
    'tailed_destination_object_rejected' => !isset($spanRows[2]['link_destination_page']),
    'clean_direct_uri_promoted' => str_contains($wordpressText, 'https://example.com/clean-docs-indirect-tail-boundary'),
    'tailed_payload_in_review' => str_contains($encodedReview, 'tailed-action-object-promote')
        || str_contains($encodedReview, 'tailed-destination-object-promote'),
    'visible_text_imported' => str_contains($visibleText, 'Clean docs Tailed action object Tailed destination object'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Tailed action object review')
        || str_contains($visibleText, 'Tailed destination object review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-indirect-object-tail-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
