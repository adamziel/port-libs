<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Chained link Chained highlight Cyclic decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots 6 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n10 0 R\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 164 718] /Contents (Chained link review) /A << /S /URI /URI (https://example.com/chained-annots-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [174 700 314 718] /QuadPoints [174 718 314 718 174 700 314 700] /Contents (Chained highlight review) /T (Import QA) /C [1 0.85 0] >>\nendobj\n"
    . "10 0 obj\n[7 0 R 8 0 R 12 0 R]\nendobj\n"
    . "12 0 obj\n13 0 R\nendobj\n"
    . "13 0 obj\n12 0 R\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 420.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'spans' => [
                ['text' => 'Chained link', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Chained highlight', 'bbox' => [174.0, 700.0, 314.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Cyclic decoy', 'bbox' => [324.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$markupPages = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$reviewJson = json_encode([$annotationPages, $linkPages, $markupPages, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-link-markup-reference-chain-boundary',
    'native_boundary' => 'Page /Annots references may resolve through bounded indirect references to annotation arrays before link and markup review',
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($linkPages[0]['links'] ?? [], 'annotation_object'),
    'markup_objects' => array_column($markupPages[0]['markups'] ?? [], 'annotation_object'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'reference_cycle_payload_promoted' => str_contains($reviewJson, '12 0 R') || str_contains($reviewJson, '13 0 R'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Chained link review')
        || str_contains($plainText, 'Chained highlight review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_objects'] ?? []) !== [7, 8]) {
    throw new RuntimeException('Expected chained /Annots reference array to expose the current link and highlight annotations.');
}
if (($summary['promoted_link_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected only the URI Link annotation to be promoted to a WordPress span.');
}
if (($summary['markup_objects'] ?? []) !== [8]) {
    throw new RuntimeException('Expected the Highlight annotation to be preserved as review metadata.');
}
if (($summary['wordpress_text'] ?? null) !== '[Chained link](https://example.com/chained-annots-link) Chained highlight Cyclic decoy') {
    throw new RuntimeException('Expected WordPress paragraph text to promote the link and keep highlight review out of visible text.');
}
if (($summary['reference_cycle_payload_promoted'] ?? true) !== false) {
    throw new RuntimeException('Cyclic annotation references must remain bounded and out of review metadata.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation review text must stay out of visible WordPress content.');
}

echo '<!-- markerpdf-pdf-annotation-link-markup-reference-chain-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
