<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Styled docs Borderless docs Hidden review) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Styled link border review) /T (Import QA) /H /O /C 60 0 R /BS 61 0 R /A << /S /URI /URI (https://example.com/styled-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 262 718] /Contents <426f726465726c65737320726576696577> /T (Accessibility QA) /H /N /C [] /Border 62 0 R /A << /S /URI /URI (https://example.com/borderless-docs) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /F 2 /Contents (Hidden presentation must not promote) /H /P /C [1 0 0] /BS << /W 4 /S /U >> /A << /S /URI /URI (https://example.com/hidden-presentation) >> >>\nendobj\n"
    . "60 0 obj\n[0.2 0.4 0.8]\nendobj\n"
    . "61 0 obj\n<< /W 2 /S /D /D [3 1] >>\nendobj\n"
    . "62 0 obj\n[4 5 0 [2 2]]\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Styled docs', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Borderless docs', 'bbox' => [166.0, 700.0, 262.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden review', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-presentation-boundary',
    'native_boundary' => 'PDF Link annotation /H, /C, /BS, /Border, /Contents, and /T are preserved as review-only WordPress span context',
    'link_count' => count($links[0]['links'] ?? []),
    'first_highlight_mode_label' => $links[0]['links'][0]['highlight_mode_label'] ?? null,
    'first_border_style' => $links[0]['links'][0]['border']['style'] ?? null,
    'first_border_color_hex' => $links[0]['links'][0]['border_color']['hex'] ?? null,
    'second_highlight_mode_label' => $links[0]['links'][1]['highlight_mode_label'] ?? null,
    'second_border_style' => $links[0]['links'][1]['border']['style'] ?? null,
    'second_border_color_space' => $links[0]['links'][1]['border_color']['space'] ?? null,
    'hidden_presentation_promoted' => str_contains($encodedReview, 'hidden-presentation')
        || str_contains($encodedReview, 'Hidden presentation must not promote'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_presentation_metadata' => !str_contains($visibleText, 'Styled link border review')
        && !str_contains($visibleText, 'Borderless review')
        && !str_contains($visibleText, 'hidden-presentation'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['link_count'] !== 2
    || $summary['first_highlight_mode_label'] !== 'outline'
    || $summary['first_border_style'] !== 'dashed'
    || $summary['second_border_color_space'] !== 'transparent'
    || $summary['hidden_presentation_promoted'] !== false
) {
    throw new RuntimeException('Unexpected markerPDF link presentation boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-presentation-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $border = htmlspecialchars((string) ($span['link_annotation_border']['style'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $highlight = htmlspecialchars((string) ($span['link_annotation_highlight_mode_label'] ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-link-border="' . $border . '" data-markerpdf-link-highlight="' . $highlight . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
