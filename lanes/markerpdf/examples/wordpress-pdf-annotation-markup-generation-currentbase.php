<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current generated link Current generated markup Stale generated markup) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 1 R 9 1 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 1 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 198 718] /Contents (Current generation link review) /A << /S /URI /URI (https://example.com/current-generated-link) >> >>\nendobj\n"
    . "9 1 obj\n<< /Type /Annot /Subtype /Highlight /Rect [210 700 362 718] /QuadPoints [210 718 362 718 210 700 362 700] /Contents (Current generation markup review) /T (Generation QA) /C [0.2 0.7 0.4] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [372 700 530 718] /Contents (Stale generation link review) /A << /S /URI /URI (https://example.com/stale-generated-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [372 700 530 718] /QuadPoints [372 718 530 718 372 700 530 700] /Contents (Stale generation markup review) /T (Stale QA) /C [1 0 0] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 530.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 530.0, 718.0],
            'spans' => [
                ['text' => 'Current generated link', 'bbox' => [72.0, 700.0, 198.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current generated markup', 'bbox' => [210.0, 700.0, 362.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale generated markup', 'bbox' => [372.0, 700.0, 530.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$expectedText = '[Current generated link](https://example.com/current-generated-link) Current generated markup Stale generated markup';
if (($blocks[0]['text'] ?? '') !== $expectedText) {
    throw new RuntimeException('Expected current-generation link and markup review to preserve WordPress text.');
}

$markupGeneration = $markups[0]['markups'][0]['annotation_generation'] ?? null;
$spanMarkupGeneration = $reviewPages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations'][0]['annotation_generation'] ?? null;
if ($markupGeneration !== 1 || $spanMarkupGeneration !== 1) {
    throw new RuntimeException('Expected markup annotation generation metadata to be preserved.');
}

if (str_contains($encodedReview, 'stale-generated-link') || str_contains($encodedReview, 'Stale generation markup review')) {
    throw new RuntimeException('Stale annotation generation was promoted.');
}

if (str_contains($visibleText, 'Current generation markup review') || str_contains($visibleText, 'Stale generation markup review')) {
    throw new RuntimeException('Annotation review text leaked into visible text.');
}

$summary = [
    'support_component' => 'native-pdf-markup-annotation-generation-review',
    'native_boundary' => 'text-markup annotation references preserve exact object generations through WordPress review spans',
    'annotation_generations' => array_column($annotations[0]['annotations'] ?? [], 'annotation_generation'),
    'link_generations' => array_column($links[0]['links'] ?? [], 'annotation_generation'),
    'markup_generations' => array_column($markups[0]['markups'] ?? [], 'annotation_generation'),
    'span_markup_generation' => $spanMarkupGeneration,
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'stale_generation_excluded' => !str_contains($encodedReview, 'stale-generated-link')
        && !str_contains($encodedReview, 'Stale generation markup review'),
    'annotation_review_text_excluded_from_visible_text' => !str_contains($visibleText, 'Current generation markup review')
        && !str_contains($visibleText, 'Stale generation markup review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-annotation-markup-generation-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
