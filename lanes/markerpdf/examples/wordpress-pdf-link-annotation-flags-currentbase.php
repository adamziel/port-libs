<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Printable docs Plain docs Hidden docs) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 168 718] /F 220 /Contents (Printable link flag review) /A << /S /URI /URI (https://example.com/printable-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [178 700 260 718] /Contents (Plain link flag review) /A << /S /URI /URI (https://example.com/plain-docs) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /F 36 /Contents (Hidden flag review) /A << /S /URI /URI (https://example.com/hidden-flag-docs) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Printable docs', 'bbox' => [72.0, 700.0, 168.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Plain docs', 'bbox' => [178.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden docs', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-flags-boundary',
    'native_boundary' => 'PDF Link annotation /F flags are preserved as review-only span metadata while invisible, hidden, and no-view links remain excluded from WordPress href promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'printable_link_flags' => $links[0]['links'][0]['annotation_flag_names'] ?? [],
    'printable_span_flags' => $spans[0]['link_annotation_flag_names'] ?? [],
    'plain_link_flags' => $links[0]['links'][1]['annotation_flag_names'] ?? null,
    'hidden_annotation_visibility' => $annotations[0]['annotations'][2]['annotation_visibility'] ?? null,
    'hidden_no_view_promoted' => str_contains($encodedReview, 'hidden-flag-docs')
        || str_contains($encodedReview, 'Hidden flag review'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_excludes_link_flag_metadata' => !str_contains($visibleText, 'Printable link flag review')
        && !str_contains($visibleText, 'Plain link flag review')
        && !str_contains($visibleText, 'Hidden flag review')
        && !str_contains($visibleText, 'hidden-flag-docs'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['promoted_link_objects'] !== [7, 8]
    || $summary['printable_link_flags'] !== ['print', 'no_zoom', 'no_rotate', 'read_only', 'locked']
    || $summary['hidden_annotation_visibility'] !== 'no_view'
    || $summary['hidden_no_view_promoted'] !== false
    || $summary['visible_text_excludes_link_flag_metadata'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF link annotation flags smoke output.');
}

echo '<!-- markerpdf-pdf-link-annotation-flags-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $flags = htmlspecialchars(implode(' ', $span['link_annotation_flag_names'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-annotation-flags="' . $flags . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
