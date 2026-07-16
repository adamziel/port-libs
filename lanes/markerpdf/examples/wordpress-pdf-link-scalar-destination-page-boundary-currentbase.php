<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Valid index Out direct Out action URI docs) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Appendix destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Valid scalar destination review) /Dest 1 >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 245 718] /Contents (Out of range direct destination review) /Dest 9 >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [255 700 340 718] /Contents (Out of range action destination review) /A << /S /GoTo /D 12 >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [350 700 430 718] /Contents (URI docs review) /A << /S /URI /URI (https://example.com/scalar-page-boundary) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 430.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'spans' => [
                ['text' => 'Valid index', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Out direct', 'bbox' => [160.0, 700.0, 245.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Out action', 'bbox' => [255.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' URI docs', 'bbox' => [350.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
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

$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
$annotationActionSafeties = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-scalar-destination-page-boundary',
    'native_boundary' => 'direct scalar Link annotation destinations are bounded to the current page tree before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safeties' => $annotationActionSafeties,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'valid_scalar_destination_page' => $spans[0]['link_destination_page'] ?? null,
    'out_of_range_direct_promoted' => isset($spans[1]['link_destination_page']) || str_contains($encodedLinks, 'Out of range direct destination review'),
    'out_of_range_action_promoted' => isset($spans[2]['link_destination_page']) || str_contains($encodedLinks, 'Out of range action destination review'),
    'uri_span_promoted' => ($spans[3]['link_uri'] ?? null) === 'https://example.com/scalar-page-boundary',
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => str_contains($visibleText, 'Valid index Out direct Out action URI docs')
        && str_contains($visibleText, 'Appendix destination body'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Valid scalar destination review')
        || str_contains($visibleText, 'Out of range direct destination review')
        || str_contains($visibleText, 'Out of range action destination review')
        || str_contains($visibleText, 'URI docs review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($summary['promoted_link_objects'] !== [7, 10]) {
    throw new RuntimeException('Out-of-range scalar destination annotations should not be promoted.');
}
if ($summary['valid_scalar_destination_page'] !== 1 || $summary['out_of_range_direct_promoted'] || $summary['out_of_range_action_promoted']) {
    throw new RuntimeException('Unexpected scalar destination page-boundary review output.');
}
if ($wordpressText !== 'Valid index Out direct Out action [URI docs](https://example.com/scalar-page-boundary)') {
    throw new RuntimeException('Unexpected WordPress Markdown link rendering.');
}
if ($summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Annotation review payload text leaked into visible WordPress content.');
}

echo '<!-- markerpdf-pdf-link-scalar-destination-page-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
