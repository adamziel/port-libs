<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Plugin documentation) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [5 0 R 6 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 214 718] /A << /S /URI /URI (https://example.com/docs) >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 650 180 668] /Contents (editor note only) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 214.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 214.0, 718.0],
            'spans' => [[
                'text' => 'Plugin documentation',
                'bbox' => [72.0, 700.0, 214.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

echo '<!-- markerpdf-pdf-link-annotation-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /Annots /Link URI actions applied to overlapping pdftext spans before Gutenberg paragraph rendering',
    'link_count' => count($linkedPages[0]['links'] ?? []),
    'excluded_text_annotation' => count($linkedPages[0]['links'] ?? []) === 1,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    $html = htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = preg_replace('/\[([^]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html) ?? $html;
    echo "<!-- wp:paragraph -->\n";
    echo "<p>{$html}</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
