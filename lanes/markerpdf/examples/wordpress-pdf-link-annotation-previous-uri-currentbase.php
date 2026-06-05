<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Imported jump Current docs Previous only) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Saved destination body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Imported jump review) /A << /S /GoTo /D (saved-target) >> /PA << /S /URI /URI (https://archive.example.com/original-guide) /Next << /S /URI /URI (https://archive.example.com/original-followup) >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 260 718] /Contents (Current docs review) /A << /S /URI /URI (https://example.com/current-docs) >> /PA 12 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 365 718] /Contents (Previous only review) /PA << /S /URI /URI (https://archive.example.com/previous-only) >> >>\nendobj\n"
    . "12 0 obj\n<< /S /URI /URI (https://archive.example.com/old-current-docs) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(saved-target) [4 0 R /FitH 720]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 365.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 365.0, 718.0],
            'spans' => [
                ['text' => 'Imported jump', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Current docs', 'bbox' => [170.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Previous only', 'bbox' => [270.0, 700.0, 365.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedLinks = json_encode($links, JSON_UNESCAPED_SLASHES) ?: '';
$encodedPages = json_encode($linkedPages, JSON_UNESCAPED_SLASHES) ?: '';
$previousUriActions = array_merge(
    $annotations[0]['annotations'][0]['previous_uri_actions'] ?? [],
    $annotations[0]['annotations'][1]['previous_uri_actions'] ?? [],
    $annotations[0]['annotations'][2]['previous_uri_actions'] ?? []
);

if ($wordpressText !== 'Imported jump [Current docs](https://example.com/current-docs) Previous only') {
    throw new RuntimeException('Expected only the current URI action to become a WordPress Markdown link.');
}
if (!str_contains($encodedLinks, 'original-guide') || !str_contains($encodedPages, 'old-current-docs')) {
    throw new RuntimeException('Expected previous URI actions to stay available as review-only metadata.');
}
if (str_contains($wordpressText, 'archive.example.com') || str_contains($encodedLinks, 'previous-only')) {
    throw new RuntimeException('Previous-only /PA link must not be promoted into clickable WordPress content.');
}
if (str_contains($visibleText, 'archive.example.com') || str_contains($visibleText, 'Current docs review')) {
    throw new RuntimeException('Annotation previous-URI metadata leaked into visible text.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-previous-uri-boundary',
    'native_boundary' => 'Link annotation /PA previous URI actions are review-only metadata; /A or /Dest remains the only primary WordPress promotion source',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'previous_uri_action_uris' => array_column($previousUriActions, 'uri'),
    'previous_uri_actions_promoted' => str_contains($wordpressText, 'archive.example.com') || str_contains($encodedLinks, 'previous-only'),
    'current_uri_promoted' => str_contains($wordpressText, 'https://example.com/current-docs'),
    'local_destination_stays_non_href' => !isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']),
    'previous_metadata_on_link_span' => ($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_previous_uri_actions'][0]['uri'] ?? null) === 'https://archive.example.com/old-current-docs',
    'visible_text_imported' => str_contains($visibleText, 'Imported jump Current docs Previous only'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'archive.example.com') || str_contains($visibleText, 'Current docs review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-previous-uri-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
