<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Review jump docs markup) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Destination page) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 14 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R 8 0 R 9 0 R << /Type /Annot /Subtype /Text /Rect [300 700 340 718] /Contents (sticky only) /A 10 0 R >>] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Dest [4 0 R /FitH 720] /AA << /E << /S /URI /URI (https://example.com/hover) >> /D 12 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [156 700 210 718] /A << /S /URI /URI (https://example.com/docs) /Next << /S /GoTo /D (wp-review-target) >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [216 700 286 718] /QuadPoints [216 718 286 718 216 700 286 700] /Contents (Editorial markup destination review) /T (Import QA) /A << /S /GoTo /D (wp-review-target) >> /AA << /E << /S /URI /URI (https://example.com/markup-hover) >> /D << /S /JavaScript /JS (markupDownReview\\(\\)) >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [72 640 180 658] /QuadPoints [72 658 180 658 72 640 180 640] /Contents (stale detached highlight) /AA << /E << /S /JavaScript /JS (staleHover\\(\\)) >> >> >>\nendobj\n"
    . "11 0 obj\n[4 0 R /XYZ 36 700 0]\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (linkDownReview\\(\\)) >>\nendobj\n"
    . "14 0 obj\n<< /Names [(wp-review-target) 11 0 R] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 286.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 286.0, 718.0],
            'spans' => [
                ['text' => 'Review jump', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' docs', 'bbox' => [156.0, 700.0, 210.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' markup', 'bbox' => [216.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$markedPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($markedPages));

$links = $markedPages[0]['links'] ?? [];
$markups = $markedPages[0]['markup_annotations'] ?? [];
$markupReview = $markedPages[0]['blocks'][0]['lines'][0]['spans'][2]['review_annotations'][0] ?? [];

echo '<!-- markerpdf-pdf-link-markup-aa-destination-boundaries ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'native_boundary' => 'PDF link/text-markup annotation destinations and /AA actions are review metadata before WordPress rendering',
    'link_count' => count($links),
    'local_destination_page' => $links[0]['destination_page'] ?? null,
    'local_destination_view_mode' => $links[0]['view_mode'] ?? null,
    'link_additional_action_safety' => array_column($links[0]['additional_actions'] ?? [], 'safety'),
    'uri_link' => $links[1]['uri'] ?? null,
    'uri_chained_destination' => $links[1]['actions'][1]['destination'] ?? null,
    'markup_count' => count($markups),
    'markup_destination' => $markupReview['actions'][0]['destination'] ?? null,
    'markup_destination_view_mode' => $markupReview['actions'][0]['view_mode'] ?? null,
    'markup_additional_action_safety' => array_column($markupReview['additional_actions'] ?? [], 'safety'),
    'stale_unreferenced_annotation_excluded' => count($markups) === 1,
    'visible_text_excludes_action_scripts' => !str_contains($blocks[0]['text'] ?? '', 'Review()') && !str_contains($blocks[0]['text'] ?? '', 'staleHover'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($markedPages[0]['blocks'][0]['lines'][0]['spans'] as $span) {
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

    $reviews = $span['review_annotations'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        $review = $reviews[0];
        echo '<mark data-markerpdf-review="' . htmlspecialchars((string) ($review['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-markerpdf-destination="' . htmlspecialchars((string) ($review['actions'][0]['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</mark>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
