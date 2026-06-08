<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean link Tailed decoy Direct link) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 11 0 R 12 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 152 718] /Contents (Clean link review) /A << /S /URI /URI (https://example.com/clean-link) >> >>\nendobj\n"
    . "11 0 obj\n8 0 R 9 0 R\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [164 700 254 718] /Contents (Tailed first decoy review) /A << /S /URI /URI (https://example.com/tailed-first-decoy) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [164 700 254 718] /Contents (Tailed second decoy review) /A << /S /URI /URI (https://example.com/tailed-second-decoy) >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [266 700 348 718] /Contents (Direct link review) /A << /S /URI /URI (https://example.com/direct-link) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 348.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 348.0, 718.0],
            'spans' => [
                ['text' => 'Clean link', 'bbox' => [72.0, 700.0, 152.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed decoy', 'bbox' => [164.0, 700.0, 254.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct link', 'bbox' => [266.0, 700.0, 348.0, 718.0], 'font' => 'Helvetica'],
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
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-annotation-link-tailed-reference-boundary',
    'native_boundary' => 'Page /Annots references whose target object contains trailing top-level operands are rejected before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'tailed_reference_rejected' => !str_contains($encodedReview, 'tailed-first-decoy')
        && !str_contains($encodedReview, 'tailed-second-decoy'),
    'tailed_span_unlinked' => !isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Clean link Tailed decoy Direct link'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Tailed first decoy review')
        || str_contains($visibleText, 'Tailed second decoy review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-annotation-link-tailed-reference-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
