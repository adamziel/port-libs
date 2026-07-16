<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean docs Control docs Mail control Relative control) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Clean docs link) /A << /S /URI /URI (https://example.com/clean-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Control newline link) /A << /S /URI /URI (https://example.com/control\\njavascript:alert\\(1\\)) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 350 718] /Contents (Control tab mail link) /A << /S /URI /URI (mailto:import@example.com\\tjavascript:alert\\(2\\)) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [360 700 470 718] /Contents (Control relative link) /A << /S /URI /URI (/wp-content/uploads/file.pdf\\rjavascript:alert\\(3\\)) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 470.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'spans' => [
                ['text' => 'Clean docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Control docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Mail control', 'bbox' => [260.0, 700.0, 350.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Relative control', 'bbox' => [360.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
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
$encodedBlocks = json_encode($blocks, JSON_UNESCAPED_SLASHES) ?: '';

$annotationSafeties = array_map(
    static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-annotation-uri-control-boundary',
    'native_boundary' => 'Link annotation URI actions containing ASCII control or space bytes remain review-only before WordPress Markdown link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safeties' => $annotationSafeties,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uri' => $links[0]['links'][0]['uri'] ?? null,
    'control_newline_blocked' => ($annotationSafeties[1] ?? null) === 'blocked-unsafe-uri',
    'control_tab_blocked' => ($annotationSafeties[2] ?? null) === 'blocked-unsafe-uri',
    'control_relative_blocked' => ($annotationSafeties[3] ?? null) === 'blocked-unsafe-uri',
    'unsafe_control_uri_promoted' => str_contains($encodedBlocks, 'javascript:alert')
        || str_contains($encodedBlocks, "\n")
        || str_contains($encodedBlocks, "\r")
        || str_contains($encodedBlocks, "\t"),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Clean docs Control docs Mail control Relative control'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Control newline link')
        || str_contains($visibleText, 'Control tab mail link')
        || str_contains($visibleText, 'Control relative link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-uri-control-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
