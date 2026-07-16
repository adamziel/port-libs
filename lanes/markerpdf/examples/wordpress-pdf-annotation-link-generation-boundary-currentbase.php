<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Current generated link Current generated markup Stale generated link) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 1 R 9 1 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 1 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 198 718] /Contents (Current generation link review) /A << /S /URI /URI (https://example.com/current-generated-link) >> >>\nendobj\n"
    . "9 1 obj\n<< /Type /Annot /Subtype /Highlight /Rect [210 700 362 718] /QuadPoints [210 718 362 718 210 700 362 700] /Contents (Current generation markup review) /T (Generation QA) /C [0.2 0.7 0.4] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [372 700 510 718] /Contents (Stale generation link review) /A << /S /URI /URI (https://example.com/stale-generated-link) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [372 700 510 718] /QuadPoints [372 718 510 718 372 700 510 700] /Contents (Stale generation markup review) /T (Stale QA) /C [1 0 0] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 510.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 510.0, 718.0],
            'spans' => [
                ['text' => 'Current generated link', 'bbox' => [72.0, 700.0, 198.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current generated markup', 'bbox' => [210.0, 700.0, 362.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale generated link', 'bbox' => [372.0, 700.0, 510.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$summaryJson = json_encode([
    'support_component' => 'native-pdf-page-annotation-generation-boundary',
    'native_boundary' => 'page /Annots indirect references resolve by exact object generation before WordPress link and markup promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_contents' => array_column($annotations[0]['annotations'] ?? [], 'contents'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'markup_contents' => array_column($markups[0]['markups'] ?? [], 'contents'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'stale_generation_uri_excluded' => !str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'stale-generated-link'),
    'stale_generation_markup_excluded' => !str_contains(json_encode($markups, JSON_UNESCAPED_SLASHES) ?: '', 'Stale generation markup review'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'Current generation link review')
        && !str_contains($visibleText, 'Stale generation link review')
        && !str_contains($visibleText, 'Stale generation markup review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-generation-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
