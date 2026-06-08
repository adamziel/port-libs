<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Scalar next Dict next Valid next) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Named target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Scalar next review) /A << /S /URI /URI (https://example.com/docs-scalar-next) /Next (named-target) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Dict next review) /A << /S /URI /URI (https://example.com/docs-dict-next) /Next << /D (named-target) >> >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 350 718] /Contents (Valid next review) /A << /S /URI /URI (https://example.com/docs-valid-next) /Next << /S /GoTo /D (named-target) >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(named-target) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 350.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 350.0, 718.0],
            'spans' => [
                ['text' => 'Scalar next', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Dict next', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Valid next', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
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
$actionSafetyRows = array_map(
    static fn (array $link): array => array_column($link['actions'] ?? [], 'safety'),
    $links[0]['links'] ?? []
);

if ($wordpressText !== '[Scalar next](https://example.com/docs-scalar-next) [Dict next](https://example.com/docs-dict-next) [Valid next](https://example.com/docs-valid-next)') {
    throw new RuntimeException('Expected only primary URI actions to become WordPress Markdown links.');
}
if (($actionSafetyRows[0] ?? []) !== ['review-uri', 'malformed-action-dictionary']) {
    throw new RuntimeException('Expected scalar /Next value to stay malformed review metadata.');
}
if (($actionSafetyRows[1] ?? []) !== ['review-uri', 'malformed-action-dictionary']) {
    throw new RuntimeException('Expected destination dictionary /Next value without /S to stay malformed review metadata.');
}
if (($actionSafetyRows[2] ?? []) !== ['review-uri', 'local-destination']) {
    throw new RuntimeException('Expected valid /Next /S /GoTo action dictionary to preserve local destination review.');
}
if (str_contains($wordpressText, 'named-target') || str_contains($visibleText, 'Scalar next review')) {
    throw new RuntimeException('Action-chain review metadata leaked into imported WordPress text.');
}

$summary = [
    'support_component' => 'native-pdf-link-next-action-value-boundary',
    'native_boundary' => 'Link annotation /Next action chains accept only action dictionaries or arrays of action dictionaries; scalar destinations and dictionaries without /S remain malformed review metadata',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'action_safety_rows' => $actionSafetyRows,
    'scalar_next_local_destination_promoted' => in_array('local-destination', $actionSafetyRows[0] ?? [], true),
    'dictionary_without_s_next_local_destination_promoted' => in_array('local-destination', $actionSafetyRows[1] ?? [], true),
    'valid_goto_next_preserved' => in_array('local-destination', $actionSafetyRows[2] ?? [], true),
    'visible_text_imported' => str_contains($visibleText, 'Scalar next Dict next Valid next'),
    'review_metadata_visible' => str_contains($visibleText, 'Scalar next review') || str_contains($visibleText, 'named-target'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-next-action-value-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
