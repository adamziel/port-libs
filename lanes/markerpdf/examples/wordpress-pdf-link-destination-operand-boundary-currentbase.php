<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Self indirect Other indirect Tailed coordinate Safe URI) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 11 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Self indirect destination review) /Dest [3 0 R /XYZ 20 0 R 21 0 R 22 0 R] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 300 718] /Contents (Other indirect destination review) /Dest [4 0 R /FitH 23 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [310 700 430 718] /Contents (Tailed coordinate destination review) /Dest [4 0 R /FitH 24 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [440 700 510 718] /Contents (Safe URI review) /A << /S /URI /URI (https://example.com/safe-link) >> >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "20 0 obj\n72\nendobj\n"
    . "21 0 obj\n720\nendobj\n"
    . "22 0 obj\nnull\nendobj\n"
    . "23 0 obj\n700\nendobj\n"
    . "24 0 obj\n640 /PrivateTail\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 510.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'spans' => [
                ['text' => 'Self indirect', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Other indirect', 'bbox' => [184.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed coordinate', 'bbox' => [310.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe URI', 'bbox' => [440.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedPromotedRows = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$annotationActionSafety = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-destination-operand-boundary',
    'native_boundary' => 'explicit Link destination arrays resolve exact indirect numeric coordinates but reject scalar coordinate objects with top-level trailing operands before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safety' => $annotationActionSafety,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'self_indirect_destination_promoted' => ($spans[0]['link_destination_page'] ?? null) === 0
        && ($spans[0]['link_view_mode'] ?? null) === 'XYZ',
    'other_indirect_destination_promoted' => ($spans[1]['link_destination_page'] ?? null) === 1
        && ($spans[1]['link_view_mode'] ?? null) === 'FitH',
    'tailed_coordinate_promoted' => isset($spans[2]['link_destination_page'])
        || str_contains($encodedPromotedRows, 'PrivateTail')
        || str_contains($encodedPromotedRows, 'Tailed coordinate destination review'),
    'safe_uri_promoted' => ($spans[3]['link_uri'] ?? null) === 'https://example.com/safe-link',
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => str_contains($plainText, 'Self indirect Other indirect Tailed coordinate Safe URI')
        && str_contains($plainText, 'Indirect destination target body'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Self indirect destination review')
        || str_contains($plainText, 'Other indirect destination review')
        || str_contains($plainText, 'Tailed coordinate destination review')
        || str_contains($plainText, 'Safe URI review')
        || str_contains($plainText, 'safe-link')
        || str_contains($plainText, 'PrivateTail'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['annotation_action_safety'] ?? []) !== [['local-destination'], ['local-destination'], [], ['review-uri']]) {
    throw new RuntimeException('Expected tailed scalar destination coordinate to remain unpromoted annotation metadata.');
}
if (($summary['promoted_link_objects'] ?? []) !== [7, 8, 10]) {
    throw new RuntimeException('Expected only valid local destinations and safe URI link to promote.');
}
if (!$summary['self_indirect_destination_promoted'] || !$summary['other_indirect_destination_promoted'] || !$summary['safe_uri_promoted']) {
    throw new RuntimeException('Expected valid indirect destination coordinates and safe URI to promote.');
}
if ($summary['tailed_coordinate_promoted'] || $summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Tailed destination coordinate payload leaked into promoted WordPress rows or visible text.');
}
if ($wordpressText !== 'Self indirect Other indirect Tailed coordinate [Safe URI](https://example.com/safe-link)') {
    throw new RuntimeException('Unexpected WordPress Markdown for destination operand boundary.');
}

echo '<!-- markerpdf-pdf-link-destination-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
