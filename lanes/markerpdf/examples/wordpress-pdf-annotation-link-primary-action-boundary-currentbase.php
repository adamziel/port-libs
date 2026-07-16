<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Safe docs Script chain Launch chain) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chained local destination) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe docs primary link) /A << /S /URI /URI (https://example.com/direct-safe) /Next << /S /JavaScript /JS (safeFollowupReview\\(\\)) >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 255 718] /Contents (Script chain review) /A << /S /JavaScript /JS (primaryScriptReview\\(\\)) /Next [10 0 R << /S /GoTo /D (safe-local) >>] >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [265 700 370 718] /Contents (Launch chain review) /A << /S /Launch /F (review-helper.exe) /Next 11 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/chained-safe) >>\nendobj\n"
    . "11 0 obj\n<< /S /GoToR /F 12 0 R /D [3 /FitH 720] /NewWindow false >>\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (remote-review.pdf) /UF <FEFF00720065006D006F00740065002D007200650076006900650077002E007000640066> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(safe-local) [4 0 R /XYZ 36 700 0]] >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 370.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 370.0, 718.0],
            'spans' => [
                ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Script chain', 'bbox' => [160.0, 700.0, 255.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Launch chain', 'bbox' => [265.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$links = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
$linkedPages = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$annotationActions = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);

if (($blocks[0]['text'] ?? '') !== '[Safe docs](https://example.com/direct-safe) Script chain Launch chain') {
    throw new RuntimeException('Expected only the direct primary URI to be emitted in WordPress text.');
}
if (str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'chained-safe') || str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'remote-review.pdf')) {
    throw new RuntimeException('Chained safe actions after blocked primary actions must stay review-only.');
}
if (str_contains($visibleText, 'primaryScriptReview') || str_contains($visibleText, 'review-helper.exe') || str_contains($visibleText, 'chained-safe')) {
    throw new RuntimeException('Action-chain review payload leaked into visible PDF text.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-link-primary-action-boundary',
    'native_boundary' => 'Link annotation span promotion uses only direct primary URI/GoTo/GoToR actions; safe /Next actions after blocked JavaScript or Launch primaries remain review metadata',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safety_chains' => $annotationActions,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'chained_uri_review_only' => !str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'chained-safe'),
    'remote_gotor_review_only' => !str_contains(json_encode($links, JSON_UNESCAPED_SLASHES) ?: '', 'remote-review.pdf'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'primaryScriptReview')
        && !str_contains($visibleText, 'review-helper.exe')
        && !str_contains($visibleText, 'chained-safe'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-primary-action-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
