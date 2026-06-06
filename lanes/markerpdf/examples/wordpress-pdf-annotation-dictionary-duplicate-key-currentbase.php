<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current docs Current highlight Stale duplicate) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [270 700 390 718] /F 2 /Contents (Stale duplicate Link review) /C [1 0 0] /A << /S /URI /URI (https://stale.example.com/first-link) >> /Subtype /Link /Rect [72 700 150 718] /F 4 /Contents (Current duplicate Link review) /C [0 0.25 1] /A << /S /URI /URI (https://example.com/current-duplicate-annotation-key-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [270 700 390 718] /QuadPoints [270 718 390 718 270 700 390 700] /Contents (Stale duplicate markup review) /T (Stale QA) /C [1 0 0] /CA 0.1 /F 2 /Subtype /Highlight /Rect [160 700 260 718] /QuadPoints [160 718 260 718 160 700 260 700] /Contents (Current duplicate Highlight review) /T (Current QA) /Subj (Accepted highlight) /C [0.1 0.8 0.3] /CA 0.55 /F 4 /Border [0 0 1 [2 1]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 390.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 390.0, 718.0],
            'spans' => [
                ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current highlight', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale duplicate', 'bbox' => [270.0, 700.0, 390.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-dictionary-duplicate-key-boundary',
    'native_boundary' => 'Annotation dictionaries select the last top-level duplicate keys before WordPress link and text-markup promotion',
    'annotation_subtypes' => array_column($annotations[0]['annotations'] ?? [], 'subtype'),
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'markup_annotation_objects' => array_column($markups[0]['markups'] ?? [], 'annotation_object'),
    'link_uri' => $spans[0]['link_uri'] ?? null,
    'highlight_review' => $spans[1]['review_annotations'][0]['contents'] ?? null,
    'stale_first_keys_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'Stale duplicate Link review')
        && !str_contains($encodedReview, 'Stale duplicate markup review'),
    'stale_span_unlinked' => !isset($spans[2]['link_uri']) && !isset($spans[2]['review_annotations']),
    'wordpress_markdown' => $blocks[0]['text'] ?? null,
    'annotation_payload_text_visible' => str_contains($visibleText, 'Current duplicate Link review')
        || str_contains($visibleText, 'Current duplicate Highlight review')
        || str_contains($visibleText, 'Stale duplicate Link review')
        || str_contains($visibleText, 'Stale duplicate markup review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

if (($summary['link_uri'] ?? null) !== 'https://example.com/current-duplicate-annotation-key-link') {
    throw new RuntimeException('Expected current duplicate-key link annotation to promote.');
}
if (($summary['highlight_review'] ?? null) !== 'Current duplicate Highlight review') {
    throw new RuntimeException('Expected current duplicate-key markup annotation to attach review metadata.');
}
if (($summary['stale_first_keys_excluded'] ?? false) !== true || ($summary['stale_span_unlinked'] ?? false) !== true) {
    throw new RuntimeException('Stale first duplicate annotation keys must stay out of WordPress review output.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation review payload text must stay out of visible WordPress text.');
}

echo '<!-- markerpdf-pdf-annotation-dictionary-duplicate-key-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        echo '<a href="' . htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</a>';
        continue;
    }

    $reviews = $span['review_annotations'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        echo '<mark data-markerpdf-review="' . htmlspecialchars((string) ($reviews[0]['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $text . '</mark>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
