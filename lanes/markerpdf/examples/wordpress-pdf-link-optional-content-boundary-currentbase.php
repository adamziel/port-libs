<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Visible layer Off layer Membership gated) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OCProperties << /OCGs [20 0 R 21 0 R 22 0 R] /D << /BaseState /OFF /ON [20 0 R 22 0 R] /OFF [21 0 R] /Order [20 0 R 21 0 R 22 0 R] >> >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /OC 20 0 R /Rect [72 700 164 718] /Contents (Visible optional-content link review) /A << /S /URI /URI (https://example.com/visible-layer-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /OC 21 0 R /Rect [174 700 250 718] /Contents (Off optional-content link review) /A << /S /URI /URI (https://example.com/off-layer-docs) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /OC << /Type /OCMD /OCGs [20 0 R 21 0 R] /P /AllOn >> /Rect [260 700 386 718] /Contents (Membership optional-content link review) /A << /S /URI /URI (https://example.com/membership-gated-docs) >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Visible Import Links) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Hidden Review Links) >>\nendobj\n"
    . "22 0 obj\n<< /Type /OCG /Name (Unused Visible Layer) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 386.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 386.0, 718.0],
            'spans' => [
                ['text' => 'Visible layer', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Off layer', 'bbox' => [174.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Membership gated', 'bbox' => [260.0, 700.0, 386.0, 718.0], 'font' => 'Helvetica'],
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
    'support_component' => 'native-pdf-link-optional-content-boundary',
    'native_boundary' => 'PDF Link annotation /OC OCG and OCMD default-view visibility gates WordPress span promotion',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_uri' => $spans[0]['link_uri'] ?? null,
    'visible_link_optional_content_visible' => $spans[0]['link_optional_content_visible'] ?? null,
    'off_layer_link_promoted' => isset($spans[1]['link_uri']),
    'membership_link_promoted' => isset($spans[2]['link_uri']),
    'off_layer_review_excluded' => !str_contains($encodedReview, 'off-layer-docs')
        && !str_contains($encodedReview, 'Off optional-content link review'),
    'membership_review_excluded' => !str_contains($encodedReview, 'membership-gated-docs')
        && !str_contains($encodedReview, 'Membership optional-content link review'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_link_metadata' => str_contains($visibleText, 'Visible layer Off layer Membership gated')
        && !str_contains($visibleText, 'visible-layer-docs')
        && !str_contains($visibleText, 'off-layer-docs')
        && !str_contains($visibleText, 'membership-gated-docs'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['promoted_link_objects'] !== [7]
    || $summary['promoted_uri'] !== 'https://example.com/visible-layer-docs'
    || $summary['visible_link_optional_content_visible'] !== true
    || $summary['off_layer_link_promoted'] !== false
    || $summary['membership_link_promoted'] !== false
    || $summary['off_layer_review_excluded'] !== true
    || $summary['membership_review_excluded'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF optional-content Link annotation boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-optional-content-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-oc-visible="true">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
