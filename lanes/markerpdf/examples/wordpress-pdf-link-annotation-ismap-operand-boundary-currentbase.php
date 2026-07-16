<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Static docs Indirect map Tailed map Named map) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Static IsMap false review) /A << /S /URI /URI (https://example.com/static-ismap-docs) /IsMap false >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Indirect IsMap true review) /A << /S /URI /URI (https://maps.example.com/indirect-map) /IsMap 20 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Tailed IsMap review) /A << /S /URI /URI (https://maps.example.com/tailed-map) /IsMap 21 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 460 718] /Contents (Named IsMap review) /A << /S /URI /URI (https://maps.example.com/named-map) /IsMap /Maybe >> >>\nendobj\n"
    . "20 0 obj\ntrue\nendobj\n"
    . "21 0 obj\ntrue 30 0 R\nendobj\n"
    . "30 0 obj\nfalse\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 460.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 460.0, 718.0],
            'spans' => [
                ['text' => 'Static docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect map', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed map', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Named map', 'bbox' => [370.0, 700.0, 460.0, 718.0], 'font' => 'Helvetica'],
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

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedPromoted = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$annotationRows = $annotations[0]['annotations'] ?? [];
$malformedIsMapCount = 0;
foreach ($annotationRows as $annotation) {
    $action = $annotation['actions'][0] ?? [];
    if (is_array($action) && ($action['uri_is_map_operand_malformed'] ?? false) === true) {
        $malformedIsMapCount++;
    }
}

if ($wordpressText !== '[Static docs](https://example.com/static-ismap-docs) Indirect map Tailed map Named map') {
    throw new RuntimeException('Expected only the non-IsMap URI annotation to become a WordPress link.');
}
if (str_contains($encodedPromoted, 'maps.example.com')) {
    throw new RuntimeException('Coordinate-dependent IsMap URI actions must stay out of promoted WordPress links.');
}
if ($malformedIsMapCount !== 2) {
    throw new RuntimeException('Expected tailed and non-boolean IsMap operands to remain review-only malformed operands.');
}
if ($plainText !== 'Static docs Indirect map Tailed map Named map') {
    throw new RuntimeException('Expected annotation review strings to stay out of visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-ismap-operand-boundary',
    'native_boundary' => 'URI Link annotation IsMap operands resolve clean indirect booleans while malformed or non-boolean operands stay coordinate-dependent review metadata before WordPress link promotion',
    'annotation_objects' => array_column($annotationRows, 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'action_safeties' => array_map(static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null, $annotationRows),
    'malformed_ismap_operand_count' => $malformedIsMapCount,
    'map_payload_promoted' => str_contains($encodedPromoted, 'maps.example.com'),
    'visible_text_imported' => $plainText === 'Static docs Indirect map Tailed map Named map',
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-ismap-operand-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
