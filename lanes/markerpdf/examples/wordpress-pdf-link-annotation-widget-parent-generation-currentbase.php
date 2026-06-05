<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Inherited docs Inherited jump Stale parent) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Exact widget destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 15 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 700 174 718] /P 3 0 R /F 4 /Parent 20 1 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [184 700 286 718] /P 3 0 R /F 4 /Parent 21 1 R >>\nendobj\n"
    . "15 0 obj\n<< /Fields [20 1 R 21 1 R] >>\nendobj\n"
    . "20 1 obj\n<< /FT /Btn /T (exact.parent.uri) /Kids [7 0 R] /A 30 1 R /AA << /U 31 1 R >> >>\nendobj\n"
    . "21 1 obj\n<< /FT /Btn /T (exact.parent.dest) /Kids [8 0 R] /Dest 44 1 R >>\nendobj\n"
    . "30 1 obj\n<< /S /URI /URI (https://example.com/current-parent-generation-link) >>\nendobj\n"
    . "31 1 obj\n<< /S /URI /URI (mailto:current-parent-generation@example.test) >>\nendobj\n"
    . "44 1 obj\n[4 0 R /FitH 720]\nendobj\n"
    . "20 0 obj\n<< /FT /Btn /T (stale.parent.uri) /Kids [7 0 R] /A 30 0 R /AA << /U 31 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /FT /Btn /T (stale.parent.dest) /Kids [8 0 R] /Dest 44 0 R >>\nendobj\n"
    . "30 0 obj\n<< /S /URI /URI (https://example.com/stale-parent-generation-link) >>\nendobj\n"
    . "31 0 obj\n<< /S /JavaScript /JS (staleFieldHover\\(\\)) >>\nendobj\n"
    . "44 0 obj\n[3 0 R /Fit]\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 372.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 372.0, 718.0],
            'spans' => [
                ['text' => 'Inherited docs', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Inherited jump', 'bbox' => [184.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale parent', 'bbox' => [296.0, 700.0, 372.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (($links[0]['links'][0]['uri'] ?? null) !== 'https://example.com/current-parent-generation-link') {
    throw new RuntimeException('Expected the widget to inherit the exact generation-one parent URI action.');
}
if (($links[0]['links'][1]['destination_page'] ?? null) !== 1) {
    throw new RuntimeException('Expected the widget to inherit the exact generation-one parent destination.');
}
if (str_contains($encodedReview, 'stale-parent-generation-link') || str_contains($encodedReview, 'staleFieldHover')) {
    throw new RuntimeException('Stale generation-zero parent action metadata leaked into WordPress link review.');
}

$summary = [
    'support_component' => 'native-pdf-link-widget-parent-generation-boundary',
    'native_boundary' => 'Widget link inheritance resolves /Parent by object and generation before WordPress span promotion',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'parent_generations' => array_column($links[0]['links'] ?? [], 'widget_field_parent_generation'),
    'promoted_uri' => $links[0]['links'][0]['uri'] ?? null,
    'promoted_destination_page' => $links[0]['links'][1]['destination_page'] ?? null,
    'stale_parent_generation_excluded' => !str_contains($encodedReview, 'stale-parent-generation-link')
        && !str_contains($encodedReview, 'staleFieldHover'),
    'visible_text_excludes_action_payloads' => !str_contains($plainText, 'current-parent-generation-link')
        && !str_contains($plainText, 'stale-parent-generation-link')
        && !str_contains($plainText, 'staleFieldHover'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-widget-parent-generation-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
