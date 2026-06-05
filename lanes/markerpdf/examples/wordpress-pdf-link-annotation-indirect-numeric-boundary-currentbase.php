<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect docs Middle gap Indirect quad Wrong generation) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 0 R 51 0 R 52 0 R 53 0 R] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [20 0 R 21 0 R 22 0 R 23 0 R] /Contents (Indirect numeric URI review) /C [90 0 R 91 0 R 92 0 R] /A << /S /URI /URI (https://example.com/indirect-numeric-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [30 0 R 31 0 R 32 0 R 33 0 R] /QuadPoints [40 0 R 41 0 R 42 0 R 43 0 R 44 0 R 45 0 R 46 0 R 47 0 R] /A << /S /URI /URI (https://example.com/indirect-quad-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 1 R 61 1 R 62 1 R 63 1 R] /Contents (Wrong generation numeric decoy) /A << /S /URI /URI (https://example.com/wrong-generation-link) >> >>\nendobj\n"
    . "20 0 obj\n72\nendobj\n21 0 obj\n200\nendobj\n22 0 obj\n150\nendobj\n23 0 obj\n218\nendobj\n"
    . "30 0 obj\n160\nendobj\n31 0 obj\n200\nendobj\n32 0 obj\n292\nendobj\n33 0 obj\n248\nendobj\n"
    . "40 0 obj\n210\nendobj\n41 0 obj\n248\nendobj\n42 0 obj\n292\nendobj\n43 0 obj\n248\nendobj\n"
    . "44 0 obj\n210\nendobj\n45 0 obj\n230\nendobj\n46 0 obj\n292\nendobj\n47 0 obj\n230\nendobj\n"
    . "50 0 obj\n50\nendobj\n51 0 obj\n50\nendobj\n52 0 obj\n300\nendobj\n53 0 obj\n260\nendobj\n"
    . "60 0 obj\n72\nendobj\n61 0 obj\n200\nendobj\n62 0 obj\n150\nendobj\n63 0 obj\n218\nendobj\n"
    . "90 0 obj\n0\nendobj\n91 0 obj\n0.25\nendobj\n92 0 obj\n0.75\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 200.0, 292.0, 248.0],
        'lines' => [[
            'bbox' => [72.0, 200.0, 292.0, 248.0],
            'spans' => [
                ['text' => 'Indirect docs', 'bbox' => [72.0, 200.0, 150.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Middle gap', 'bbox' => [160.0, 200.0, 204.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect quad', 'bbox' => [210.0, 230.0, 292.0, 248.0], 'font' => 'Helvetica'],
                ['text' => ' Wrong generation', 'bbox' => [72.0, 160.0, 190.0, 178.0], 'font' => 'Helvetica'],
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
$encodedPages = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-indirect-numeric-boundary',
    'native_boundary' => 'Link annotation /Rect, /QuadPoints, border color, and page box numeric operands resolve exact-generation indirect numbers before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_rects' => array_column($annotations[0]['annotations'] ?? [], 'rect'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'indirect_rect_promoted' => str_contains($encodedPages, 'indirect-numeric-link'),
    'indirect_quad_promoted' => str_contains($encodedPages, 'indirect-quad-link'),
    'wrong_generation_promoted' => str_contains($encodedPages, 'wrong-generation-link'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Indirect numeric URI review')
        || str_contains($visibleText, 'Wrong generation numeric decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

if (($summary['indirect_rect_promoted'] ?? false) !== true) {
    throw new RuntimeException('Expected indirect numeric /Rect link to promote to WordPress Markdown.');
}
if (($summary['indirect_quad_promoted'] ?? false) !== true) {
    throw new RuntimeException('Expected indirect numeric /QuadPoints link to promote to WordPress Markdown.');
}
if (($summary['wrong_generation_promoted'] ?? true) !== false) {
    throw new RuntimeException('Wrong-generation numeric operands must not promote a stale link.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation review payload text must stay out of visible WordPress content.');
}

echo '<!-- markerpdf-pdf-link-annotation-indirect-numeric-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
