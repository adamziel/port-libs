<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Split docs Split highlight Split note Stale decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 % split link object reference\n0 R 9 % split markup object reference\n0 R 10 % split text object reference\n0 R % 11 0 R stale comment decoy must not promote\n 12 0R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Split link review) /A 20 % split action object reference\n0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [160 700 270 718] /QuadPoints [160 718 270 718 160 700 270 700] /Contents (Split highlight review) /T (Import QA) /C [0.4 0.8 1] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Text /Rect [280 700 350 718] /Contents (Split sticky review) >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Stale comment decoy review) /A << /S /URI /URI (https://example.com/comment-decoy-annotation) >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 450 718] /Contents (Tight reference decoy review) /A << /S /URI /URI (https://example.com/tight-decoy-annotation) >> >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://example.com/comment-split-action) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 450.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 450.0, 718.0],
            'spans' => [
                ['text' => 'Split docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Split highlight', 'bbox' => [160.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Split note', 'bbox' => [280.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale decoy', 'bbox' => [360.0, 700.0, 450.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotationPages, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$wordpressText = (string) ($blocks[0]['text'] ?? '');
if ($wordpressText !== '[Split docs](https://example.com/comment-split-action) Split highlight Split note Stale decoy') {
    throw new RuntimeException('Expected only the split-reference Link annotation to become a WordPress Markdown link.');
}
if (str_contains($encodedReview, 'comment-decoy-annotation')) {
    throw new RuntimeException('Comment-only page Annots decoy was promoted into review metadata.');
}
if (str_contains($encodedReview, 'tight-decoy-annotation')) {
    throw new RuntimeException('Malformed tight page Annots reference was promoted into review metadata.');
}
if (str_contains($visibleText, 'comment-split-action') || str_contains($visibleText, 'Split highlight review')) {
    throw new RuntimeException('Annotation metadata leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-page-annots-comment-reference-boundary',
    'native_boundary' => 'PDF comments are whitespace inside indirect references in page /Annots arrays and Link /A action operands',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotationPages[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_uri' => $links[0]['links'][0]['uri'] ?? null,
    'link_action_object' => $links[0]['links'][0]['action_object'] ?? null,
    'markup_annotation_objects' => array_column($markups[0]['markups'] ?? [], 'annotation_object'),
    'comment_decoy_promoted' => str_contains($encodedReview, 'comment-decoy-annotation'),
    'tight_reference_decoy_promoted' => str_contains($encodedReview, 'tight-decoy-annotation'),
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => str_contains($visibleText, 'Split docs Split highlight Split note Stale decoy'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Split link review')
        || str_contains($visibleText, 'Split highlight review')
        || str_contains($visibleText, 'Split sticky review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-page-annots-comment-reference-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
