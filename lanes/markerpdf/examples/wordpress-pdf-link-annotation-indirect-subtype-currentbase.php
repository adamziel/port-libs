<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect subtype link Nonlink decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype 20 0 R /Rect [72 700 204 718] /Contents (Indirect annotation subtype review) /H 22 0 R /BS << /W 2 /S 23 0 R /D [3 1] >> /A << /S /URI /URI (https://example.com/indirect-annotation-subtype) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype 21 0 R /Rect [214 700 330 718] /Contents (Indirect text subtype decoy) /H 22 0 R /A << /S /URI /URI (https://example.com/indirect-text-decoy) >> >>\nendobj\n"
    . "20 0 obj\n/Link\nendobj\n"
    . "21 0 obj\n/Text\nendobj\n"
    . "22 0 obj\n/P\nendobj\n"
    . "23 0 obj\n/D\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 330.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'spans' => [
                ['text' => 'Indirect subtype link', 'bbox' => [72.0, 700.0, 204.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Nonlink decoy', 'bbox' => [214.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
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
$encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';

$summaryJson = json_encode([
    'support_component' => 'native-pdf-link-annotation-indirect-subtype-boundary',
    'native_boundary' => 'page Link annotations resolve indirect /Subtype name objects before WordPress span promotion',
    'annotation_subtypes' => array_column($annotations[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_uri' => $links[0]['links'][0]['uri'] ?? null,
    'highlight_mode' => $links[0]['links'][0]['highlight_mode'] ?? null,
    'border_style' => $links[0]['links'][0]['border']['style'] ?? null,
    'nonlink_indirect_subtype_excluded' => !str_contains($encodedLinks, 'indirect-text-decoy'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'annotation_payload_text_visible' => str_contains($visibleText, 'Indirect annotation subtype review')
        || str_contains($visibleText, 'Indirect text subtype decoy'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-link-annotation-indirect-subtype-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
