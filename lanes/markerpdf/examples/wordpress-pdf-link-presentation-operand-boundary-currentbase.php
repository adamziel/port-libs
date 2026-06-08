<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean styled Tainted color Tainted border Tainted array Tainted scalars) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R 11 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Clean styled review) /H /O /CA 0.5 /C [0.2 0.4 0.8] /BS << /W 2 /S /D /D [3 1] >> /A << /S /URI /URI (https://example.com/clean-styled) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 270 718] /Contents (Tainted color review) /C 60 0 R /BS << /W 1 /S /S >> /A << /S /URI /URI (https://example.com/tainted-color) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 380 718] /Contents (Tainted border review) /C [0.1 0.8 0.3] /BS 61 0 R /A << /S /URI /URI (https://example.com/tainted-border) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [390 700 488 718] /Contents (Tainted border array review) /C [] /Border 62 0 R /A << /S /URI /URI (https://example.com/tainted-border-array) >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /Annot /Subtype /Link /Rect [498 700 610 718] /Contents (Tainted scalar presentation review) /H /O 90 0 R /CA 0.25 90 0 R /A << /S /URI /URI (https://example.com/tainted-scalars) >> >>\nendobj\n"
    . "60 0 obj\n[1 0 0] 90 0 R\nendobj\n"
    . "61 0 obj\n<< /W 4 /S /D /D [9 9] >> 90 0 R\nendobj\n"
    . "62 0 obj\n[5 6 7 [1 1]] 90 0 R\nendobj\n"
    . "90 0 obj\n<< /S /JavaScript /JS (stalePresentationOperandReview\\(\\)) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 610.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 610.0, 718.0],
            'spans' => [
                ['text' => 'Clean styled', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tainted color', 'bbox' => [170.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tainted border', 'bbox' => [280.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tainted array', 'bbox' => [390.0, 700.0, 488.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tainted scalars', 'bbox' => [498.0, 700.0, 610.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-presentation-operand-boundary',
    'native_boundary' => 'tailed Link annotation presentation operands are fail-closed while safe URI links still promote',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'tainted_color_metadata_imported' => isset($links[0]['links'][1]['border_color']),
    'tainted_bs_metadata_imported' => isset($links[0]['links'][2]['border']),
    'tainted_border_array_metadata_imported' => isset($links[0]['links'][3]['border']),
    'tainted_scalar_metadata_imported' => isset($links[0]['links'][4]['opacity']) || isset($links[0]['links'][4]['highlight_mode']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_annotation_payloads' => !str_contains($plainText, 'Tainted color review')
        && !str_contains($plainText, 'stalePresentationOperandReview'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['promoted_link_objects'] !== [7, 8, 9, 10, 11]
    || $summary['tainted_color_metadata_imported'] !== false
    || $summary['tainted_bs_metadata_imported'] !== false
    || $summary['tainted_border_array_metadata_imported'] !== false
    || $summary['tainted_scalar_metadata_imported'] !== false
    || $summary['visible_text_excludes_annotation_payloads'] !== true
    || str_contains($encodedReview, 'stalePresentationOperandReview')
) {
    throw new RuntimeException('Unexpected markerPDF link presentation operand boundary smoke output.');
}

echo '<!-- markerpdf:pdf-link-presentation-operand-boundary-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $border = htmlspecialchars((string) ($span['link_annotation_border']['style'] ?? 'metadata-suppressed'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-link-border="' . $border . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
