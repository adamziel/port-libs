<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Direct docs Scalar action Dict action Direct dest) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs-scalar-boundary) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 255 718] /Contents (Scalar action review) /A (named-target) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [265 700 360 718] /Contents (Dictionary without S review) /A << /D (named-target) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 455 718] /Contents (Direct destination review) /Dest (named-target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(named-target) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 455.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 455.0, 718.0],
            'spans' => [
                ['text' => 'Direct docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Scalar action', 'bbox' => [160.0, 700.0, 255.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Dict action', 'bbox' => [265.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct dest', 'bbox' => [370.0, 700.0, 455.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$annotationActionSafety = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-primary-action-scalar-boundary',
    'native_boundary' => 'Link annotation /A must be an action dictionary with /S; scalar or actionless dictionaries do not donate local destinations, while /Dest remains the destination path',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safeties' => $annotationActionSafety,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'scalar_a_promoted' => isset($spans[1]['link_actions_review']) || isset($spans[1]['link_destination_page']),
    'actionless_dictionary_promoted' => isset($spans[2]['link_actions_review']) || isset($spans[2]['link_destination_page']),
    'direct_uri_promoted' => ($spans[0]['link_uri'] ?? null) === 'https://example.com/direct-docs-scalar-boundary',
    'direct_dest_review_page' => $spans[3]['link_destination_page'] ?? null,
    'visible_text_imported' => str_contains($visibleText, 'Direct docs Scalar action Dict action Direct dest')
        && str_contains($visibleText, 'Named destination body'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Scalar action review')
        || str_contains($visibleText, 'Dictionary without S review')
        || str_contains($visibleText, 'Direct destination review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $wordpressText !== '[Direct docs](https://example.com/direct-docs-scalar-boundary) Scalar action Dict action Direct dest'
    || $summary['promoted_link_objects'] !== [7, 10]
    || $summary['scalar_a_promoted'] !== false
    || $summary['actionless_dictionary_promoted'] !== false
    || $summary['direct_dest_review_page'] !== 1
    || $summary['annotation_payload_text_visible'] !== false
) {
    throw new RuntimeException('Unexpected markerPDF primary action scalar boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-primary-action-scalar-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
