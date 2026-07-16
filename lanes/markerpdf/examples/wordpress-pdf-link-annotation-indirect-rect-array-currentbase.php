<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect rect Tailed rect) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect 20 0 R /Contents (Indirect rect review) /A << /S /URI /URI (https://example.com/indirect-rect-array) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect 21 0 R /Contents (Tailed rect review) /A << /S /URI /URI (https://example.com/tailed-rect-array-decoy) >> >>\nendobj\n"
    . "20 0 obj\n[72 700 158 718]\nendobj\n"
    . "21 0 obj\n[168 700 260 718] 30 0 R\nendobj\n"
    . "30 0 obj\n<< /S /JavaScript /JS (tailedIndirectRectReview\\(\\)) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 260.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 260.0, 718.0],
            'spans' => [
                ['text' => 'Indirect rect', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed rect', 'bbox' => [168.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
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
$encodedPages = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-indirect-rect-array-boundary',
    'native_boundary' => 'Link annotation whole-object indirect /Rect arrays resolve for review and span promotion while tailed indirect arrays fail closed',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_rects' => array_column($annotations[0]['annotations'] ?? [], 'rect'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'indirect_rect_reviewed' => ($annotations[0]['annotations'][0]['rect'] ?? null) === [72.0, 700.0, 158.0, 718.0],
    'indirect_rect_promoted' => str_contains($encodedPages, 'https://example.com/indirect-rect-array'),
    'tailed_rect_promoted' => str_contains($encodedPages, 'tailed-rect-array-decoy'),
    'tailed_action_review_leaked' => str_contains($encodedPages, 'tailedIndirectRectReview'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Indirect rect review')
        || str_contains($plainText, 'Tailed rect review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['indirect_rect_reviewed'] ?? false) !== true) {
    throw new RuntimeException('Expected indirect /Rect array to be preserved in annotation review metadata.');
}
if (($summary['indirect_rect_promoted'] ?? false) !== true) {
    throw new RuntimeException('Expected indirect /Rect array link to promote into WordPress Markdown.');
}
if (($summary['tailed_rect_promoted'] ?? true) !== false) {
    throw new RuntimeException('Tailed indirect /Rect array decoy must not promote into WordPress Markdown.');
}
if (($summary['tailed_action_review_leaked'] ?? true) !== false) {
    throw new RuntimeException('Tailed indirect /Rect action payload must stay out of span review metadata.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation review payload text must stay out of visible WordPress text.');
}

echo '<!-- markerpdf-pdf-link-annotation-indirect-rect-array-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
