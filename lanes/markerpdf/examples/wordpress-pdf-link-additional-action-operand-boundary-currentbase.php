<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean hover Tailed hover Indirect tailed hover) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 156 718] /Contents (Clean hover review) /A << /S /URI /URI (https://example.com/clean-hover) >> /AA << /E 20 0 R /U 23 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [166 700 260 718] /Contents (Tailed hover review) /A << /S /URI /URI (https://example.com/tailed-hover) >> /AA << /E 21 0 R 22 0 R /U 23 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 408 718] /Contents (Indirect tailed hover review) /A << /S /URI /URI (https://example.com/indirect-tailed-hover) >> /AA 24 0 R >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://review.example.com/clean-enter) >>\nendobj\n"
    . "21 0 obj\n<< /S /URI /URI (https://review.example.com/tailed-enter-leak) /Next << /S /JavaScript /JS (tailedEnterReview\\(\\)) >> >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (https://review.example.com/private-tail) >>\nendobj\n"
    . "23 0 obj\n<< /S /URI /URI (mailto:review@example.com) >>\nendobj\n"
    . "24 0 obj\n<< /E 25 0 R 26 0 R /U 23 0 R >>\nendobj\n"
    . "25 0 obj\n<< /S /URI /URI (https://review.example.com/indirect-tailed-enter-leak) /Next << /S /JavaScript /JS (indirectTailedEnterReview\\(\\)) >> >>\nendobj\n"
    . "26 0 obj\n<< /S /Launch /F (review-helper.exe) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 408.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 408.0, 718.0],
            'spans' => [
                ['text' => 'Clean hover', 'bbox' => [72.0, 700.0, 156.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed hover', 'bbox' => [166.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect tailed hover', 'bbox' => [270.0, 700.0, 408.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

if ($wordpressText !== '[Clean hover](https://example.com/clean-hover) [Tailed hover](https://example.com/tailed-hover) [Indirect tailed hover](https://example.com/indirect-tailed-hover)') {
    throw new RuntimeException('Expected primary safe URI actions to remain promotable as WordPress Markdown links.');
}
if (($spans[0]['link_additional_actions_review'][0]['uri'] ?? null) !== 'https://review.example.com/clean-enter') {
    throw new RuntimeException('Expected a clean /AA cursor-enter action to remain available as review-only metadata.');
}
foreach ([1, 2] as $spanIndex) {
    if (($spans[$spanIndex]['link_additional_actions_review'][0]['safety'] ?? null) !== 'malformed-action-dictionary') {
        throw new RuntimeException('Expected malformed /AA cursor-enter operands to fail closed as review metadata.');
    }
    if (($spans[$spanIndex]['link_additional_actions_review'][0]['malformed_action_operand_keys'] ?? null) !== ['E']) {
        throw new RuntimeException('Expected malformed /AA cursor-enter operand key review metadata.');
    }
}
foreach (['tailed-enter-leak', 'private-tail', 'indirect-tailed-enter-leak', 'review-helper.exe', 'tailedEnterReview', 'indirectTailedEnterReview'] as $tailedPayload) {
    if (str_contains($encodedReview, $tailedPayload)) {
        throw new RuntimeException('Malformed /AA event operands must not donate tailed action payloads.');
    }
}
if ($visibleText !== 'Clean hover Tailed hover Indirect tailed hover') {
    throw new RuntimeException('Expected visible text to exclude annotation action payloads.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-additional-action-operand-boundary',
    'native_boundary' => 'Malformed Link annotation /AA event operands are rejected while primary /A URI links remain promotable',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'clean_additional_action_preserved' => ($spans[0]['link_additional_actions_review'][0]['uri'] ?? null) === 'https://review.example.com/clean-enter',
    'tailed_event_payloads_excluded' => !str_contains($encodedReview, 'tailed-enter-leak') && !str_contains($encodedReview, 'indirect-tailed-enter-leak'),
    'malformed_event_keys' => [
        $spans[1]['link_additional_actions_review'][0]['malformed_action_operand_keys'] ?? [],
        $spans[2]['link_additional_actions_review'][0]['malformed_action_operand_keys'] ?? [],
    ],
    'primary_links_promoted' => [
        $spans[0]['link_uri'] ?? null,
        $spans[1]['link_uri'] ?? null,
        $spans[2]['link_uri'] ?? null,
    ],
    'visible_text_imported' => $visibleText === 'Clean hover Tailed hover Indirect tailed hover',
    'annotation_payload_text_visible' => str_contains($visibleText, 'review.example.com') || str_contains($visibleText, 'Tailed hover review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-additional-action-operand-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
