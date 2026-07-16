<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 60 210 Td (Current parent docs Stale parent decoy) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 1 R >>\nendobj\n"
    . "2 1 obj\n<< /Type /Pages /MediaBox [0 0 320 320] /CropBox [50 50 250 250] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [60 200 174 218] /Contents (Current parent link review) /A << /S /URI /URI (https://example.com/current-parent-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [10 10 38 38] /Contents (Stale parent geometry review) /A << /S /URI /URI (https://example.com/stale-parent-link) >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 40 40] /CropBox [0 0 40 40] /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [10.0, 10.0, 246.0, 218.0],
        'lines' => [[
            'bbox' => [10.0, 10.0, 246.0, 218.0],
            'spans' => [
                ['text' => 'Current parent docs', 'bbox' => [60.0, 200.0, 174.0, 218.0], 'font' => 'Helvetica'],
                ['text' => ' Stale parent decoy', 'bbox' => [10.0, 10.0, 38.0, 38.0], 'font' => 'Helvetica'],
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

$summary = [
    'support_component' => 'native-pdf-link-parent-generation-boundary',
    'native_boundary' => 'Link annotation page geometry inherits MediaBox/CropBox/Rotate only from the exact /Parent object generation referenced by the page dictionary',
    'page_link_count' => count($links[0]['links'] ?? []),
    'promoted_annotation_objects' => array_values(array_map(
        static fn (array $link): mixed => $link['annotation_object'] ?? null,
        $links[0]['links'] ?? []
    )),
    'page_bbox' => $links[0]['links'][0]['page_bbox'] ?? null,
    'link_uri' => $links[0]['links'][0]['uri'] ?? null,
    'markdown' => $blocks[0]['text'] ?? '',
    'stale_parent_generation_excluded' => !str_contains($encodedReview, 'stale-parent-link')
        && !str_contains($encodedReview, 'Stale parent geometry review')
        && !str_contains($encodedReview, '[0,0,40,40]'),
    'stale_span_linked' => isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']),
    'visible_text_excludes_link_review_payloads' => !str_contains($plainText, 'current-parent-link')
        && !str_contains($plainText, 'stale-parent-link')
        && !str_contains($plainText, 'Current parent link review')
        && !str_contains($plainText, 'Stale parent geometry review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:pdf-link-parent-generation-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
