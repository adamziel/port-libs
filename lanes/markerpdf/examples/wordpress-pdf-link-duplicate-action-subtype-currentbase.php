<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Safe docs Duplicate subtype Safe tail) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe primary docs review) /A << /S /URI /URI (https://example.com/safe-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 282 718] /Contents (Duplicate subtype action review) /A << /S /JavaScript /JS (staleDuplicateSubtypeReview\\(\\)) /S /URI /URI (https://example.com/duplicate-subtype-should-not-promote) /Next << /S /URI /URI (https://example.com/duplicate-subtype-followup-review) >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [292 700 360 718] /Contents (Safe tail review) /A << /S /URI /URI (https://example.com/safe-tail) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Duplicate subtype', 'bbox' => [160.0, 700.0, 282.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe tail', 'bbox' => [292.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationExtractor = new PdfAnnotationExtractor();
$linkExtractor = new PdfLinkAnnotationExtractor();
$annotations = $annotationExtractor->extractPageAnnotations($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$encodedPromotedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$summary = [
    'support_component' => 'native-pdf-link-duplicate-action-subtype-boundary',
    'native_boundary' => 'duplicate /S action subtype keys stay review-only and cannot donate a WordPress href while valid sibling URI links still promote',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'malformed_action_safety' => $annotations[0]['annotations'][1]['actions'][0]['safety'] ?? null,
    'malformed_action_duplicate_keys' => $annotations[0]['annotations'][1]['actions'][0]['duplicate_keys'] ?? [],
    'duplicate_subtype_promoted' => isset($spans[1]['link_uri']),
    'valid_sibling_links_promoted' => ($spans[0]['link_uri'] ?? null) === 'https://example.com/safe-docs'
        && ($spans[2]['link_uri'] ?? null) === 'https://example.com/safe-tail',
    'chained_followup_preserved_in_annotation_review' => str_contains($encodedReview, 'duplicate-subtype-followup-review'),
    'chained_followup_excluded_from_promoted_links' => !str_contains($encodedPromotedLinks, 'duplicate-subtype-followup-review'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Safe docs Duplicate subtype Safe tail'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'duplicate-subtype-should-not-promote')
        || str_contains($visibleText, 'staleDuplicateSubtypeReview')
        || str_contains($visibleText, 'Duplicate subtype action review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($summary['promoted_link_objects'] !== [7, 9]) {
    throw new RuntimeException('Expected only valid sibling URI links to be promoted.');
}
if ($summary['duplicate_subtype_promoted'] || $summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Duplicate action subtype payload must stay out of WordPress links and visible text.');
}
if (($summary['malformed_action_safety'] ?? null) !== 'malformed-action-dictionary' || $summary['malformed_action_duplicate_keys'] !== ['S']) {
    throw new RuntimeException('Expected duplicate /S action subtype review metadata.');
}

echo '<!-- markerpdf-pdf-link-duplicate-action-subtype-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-action-review="duplicate-subtype-boundary">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
