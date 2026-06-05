<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Commented docs Comment decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot % fake dictionary close >> /Subtype /Text /Rect [250 700 340 718] /A << /S /URI /URI (https://example.com/comment-decoy-link) >>\n"
    . " /Subtype /Link /Rect [72 700 182 718] /Contents (Commented direct link review) /A 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [200 700 302 718] /F 2 /Contents (Hidden comment decoy review) /A << /S /URI /URI (https://example.com/hidden-comment-decoy) >> >>\nendobj\n"
    . "9 0 obj\n<< /S /URI % fake action close >> /URI (https://example.com/comment-decoy-action)\n"
    . " /URI (https://example.com/commented-link) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 302.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 302.0, 718.0],
            'spans' => [
                ['text' => 'Commented docs', 'bbox' => [72.0, 700.0, 182.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Comment decoy', 'bbox' => [200.0, 700.0, 302.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-comment-dictionary-boundary',
    'native_boundary' => 'PDF comments inside Link annotation and action dictionaries are skipped before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_subtypes' => array_column($annotations[0]['annotations'] ?? [], 'subtype'),
    'promoted_link_objects' => array_column($links, 'annotation_object'),
    'link_uri' => $spans[0]['link_uri'] ?? null,
    'comment_decoy_link_excluded' => !str_contains($encodedReview, 'comment-decoy-link'),
    'comment_decoy_action_excluded' => !str_contains($encodedReview, 'comment-decoy-action'),
    'hidden_comment_decoy_promoted' => isset($spans[1]['link_uri']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Commented docs Comment decoy'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Commented direct link review')
        || str_contains($visibleText, 'Hidden comment decoy review')
        || str_contains($visibleText, 'commented-link'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-comment-dictionary-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-comment-safe="true">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
