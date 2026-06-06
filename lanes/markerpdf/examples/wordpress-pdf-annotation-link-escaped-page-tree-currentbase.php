<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Escaped tree docs Escaped tree highlight Fallback stale) Tj ET';
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale fallback page body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /T#79pe /#43atalog /P#61ges 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /T#79pe /P#61ges /K#69ds [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /T#79pe /P#61ge /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Escaped page-tree link review) /A << /S /URI /URI (https://example.com/escaped-page-tree-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [184 700 328 718] /QuadPoints [184 718 328 718 184 700 328 700] /Contents (Escaped page-tree highlight review) /T (Import QA) /C [0.7 0.85 1] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [338 700 426 718] /Contents (Escaped page-tree sticky review) >>\nendobj\n"
    . "10 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 12 0 R /Annots [11 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Stale fallback link review) /A << /S /URI /URI (https://example.com/stale-fallback-link) >> >>\nendobj\n"
    . "12 0 obj\n<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 426.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 426.0, 718.0],
            'spans' => [
                ['text' => 'Escaped tree docs', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Escaped tree highlight', 'bbox' => [184.0, 700.0, 328.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Fallback stale', 'bbox' => [338.0, 700.0, 426.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = (new PdfMarkupAnnotationExtractor())->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = (new PdfMarkupAnnotationExtractor())->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';

if (($links[0]['page_object'] ?? null) !== 3 || ($markups[0]['page_object'] ?? null) !== 3) {
    throw new RuntimeException('Escaped page tree did not select the current page object.');
}
if (str_contains($encodedReview, 'stale-fallback-link') || str_contains($encodedReview, 'Stale fallback link review')) {
    throw new RuntimeException('Literal fallback page annotation leaked into WordPress review data.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-annotation-link-escaped-page-tree-boundary',
    'native_boundary' => 'escaped Catalog Pages Page and Kids names select the current page tree before annotation/link/markup promotion',
    'annotation_page_object' => $annotations[0]['page_object'] ?? null,
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'markup_objects' => array_column($markups[0]['markups'] ?? [], 'annotation_object'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'literal_fallback_page_excluded' => !str_contains($encodedReview, 'stale-fallback-link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-escaped-page-tree-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
