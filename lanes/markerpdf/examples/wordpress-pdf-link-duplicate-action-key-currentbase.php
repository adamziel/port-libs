<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate docs Duplicate jump) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate target page) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 165 718] /Contents (Duplicate action review) /A 10 0 R /A 11 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [175 700 275 718] /Contents (Duplicate destination review) /Dest (stale-target) /Dest (current-target) >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://stale.example.com/first-action) /Next << /S /Launch /F (stale-helper.exe) >> >>\nendobj\n"
    . "11 0 obj\n<< /S /URI /URI (https://stale.example.com/first-uri) /URI (https://example.com/current-duplicate-action) /Next 12 0 R /Next 13 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (staleDuplicateActionReview\\(\\)) >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D (current-target) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(current-target) [4 0 R /FitH 720] (stale-target) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 275.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 275.0, 718.0],
            'spans' => [
                ['text' => 'Duplicate docs', 'bbox' => [72.0, 700.0, 165.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Duplicate jump', 'bbox' => [175.0, 700.0, 275.0, 718.0], 'font' => 'Helvetica'],
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
    'support_component' => 'native-pdf-link-duplicate-action-key-boundary',
    'native_boundary' => 'duplicate Link annotation action keys select the last top-level entry while recording review-only duplicate metadata',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links, 'annotation_object'),
    'primary_link_uri' => $spans[0]['link_uri'] ?? null,
    'local_destination_page' => $spans[1]['link_destination_page'] ?? null,
    'annotation_duplicate_action_keys' => $spans[0]['link_duplicate_action_keys'] ?? [],
    'destination_duplicate_action_keys' => $spans[1]['link_duplicate_action_keys'] ?? [],
    'action_dictionary_duplicate_keys' => $spans[0]['link_actions_review'][0]['duplicate_keys'] ?? [],
    'stale_duplicate_action_payload_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'stale-helper.exe')
        && !str_contains($encodedReview, 'staleDuplicateActionReview')
        && !str_contains($encodedReview, 'stale-target'),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Duplicate docs Duplicate jump')
        && str_contains($visibleText, 'Current duplicate target page'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Duplicate action review')
        || str_contains($visibleText, 'Duplicate destination review')
        || str_contains($visibleText, 'current-duplicate-action'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-duplicate-action-key-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-duplicate-action-review="true">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
