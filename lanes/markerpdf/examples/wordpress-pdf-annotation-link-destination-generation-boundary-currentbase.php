<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current generation target Stale generation target) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Current destination body) Tj ET';
$stalePageOneContent = 'BT /F1 12 Tf 72 720 Td (Stale page one body) Tj ET';
$stalePageTwoContent = 'BT /F1 12 Tf 72 720 Td (Stale page two body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 1 R >>\nendobj\n"
    . "2 1 obj\n<< /Type /Pages /Kids [3 1 R 4 1 R] /Count 2 >>\nendobj\n"
    . "3 1 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 1 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 6 0 R >> >> /Contents 9 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 222 718] /Contents (Current destination generation review) /Dest [4 1 R /FitH 720] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [232 700 382 718] /Contents (Stale destination generation review) /Dest [4 0 R /FitH 111] >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [4 0 R 3 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Length " . strlen($stalePageOneContent) . " >>\nstream\n{$stalePageOneContent}\nendstream\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($stalePageTwoContent) . " >>\nstream\n{$stalePageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 382.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 382.0, 718.0],
            'spans' => [
                ['text' => 'Current generation target', 'bbox' => [72.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale generation target', 'bbox' => [232.0, 700.0, 382.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$encodedPromotedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-destination-generation-boundary',
    'native_boundary' => 'link annotation local destinations use exact page object generations from the current page tree before WordPress span promotion',
    'page_object' => $links[0]['page_object'] ?? null,
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'destination_pages' => array_column($links[0]['links'] ?? [], 'destination_page'),
    'destination_view_modes' => array_column($links[0]['links'] ?? [], 'view_mode'),
    'current_destination_promoted' => ($linkedPages[0]['blocks'][0]['lines'][0]['spans'][0]['link_destination_page'] ?? null) === 1,
    'stale_destination_promoted' => isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_destination_page']),
    'stale_annotation_review_preserved' => str_contains($encodedReview, 'Stale destination generation review'),
    'stale_generation_promoted_link_excluded' => !str_contains($encodedPromotedLinks, 'Stale destination generation review')
        && !str_contains($encodedPromotedLinks, 'FitH 111'),
    'stale_page_text_visible' => str_contains($plainText, 'Stale page one body') || str_contains($plainText, 'Stale page two body'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-annotation-link-destination-generation-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
