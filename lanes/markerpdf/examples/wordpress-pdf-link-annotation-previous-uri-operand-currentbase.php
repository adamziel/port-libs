<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Clean previous Tailed previous No previous) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 174 718] /Contents (Clean previous URI review) /A << /S /URI /URI (https://example.com/current-clean) >> /PA 20 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [184 700 304 718] /Contents (Tailed previous URI review) /A << /S /URI /URI (https://example.com/current-tailed) >> /PA 21 0 R 22 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [314 700 404 718] /Contents (No previous URI review) /A << /S /URI /URI (https://example.com/current-only) >> >>\nendobj\n"
    . "20 0 obj\n<< /S /URI /URI (https://archive.example.com/clean-previous) >>\nendobj\n"
    . "21 0 obj\n<< /S /URI /URI (https://archive.example.com/tailed-previous-leak) /Next << /S /JavaScript /JS (tailedPreviousUriReview\\(\\)) >> >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (https://archive.example.com/private-tail) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 404.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 404.0, 718.0],
            'spans' => [
                ['text' => 'Clean previous', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Tailed previous', 'bbox' => [184.0, 700.0, 304.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' No previous', 'bbox' => [314.0, 700.0, 404.0, 718.0], 'font' => 'Helvetica'],
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
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

if ($wordpressText !== '[Clean previous](https://example.com/current-clean) [Tailed previous](https://example.com/current-tailed) [No previous](https://example.com/current-only)') {
    throw new RuntimeException('Expected all primary safe URI actions to become WordPress Markdown links.');
}
if (($spans[0]['link_previous_uri_actions'][0]['uri'] ?? null) !== 'https://archive.example.com/clean-previous') {
    throw new RuntimeException('Expected the clean /PA previous URI to stay available as review-only span metadata.');
}
if (isset($spans[1]['link_previous_uri_actions']) || str_contains($encodedReview, 'tailed-previous-leak') || str_contains($encodedReview, 'tailedPreviousUriReview')) {
    throw new RuntimeException('Malformed tailed /PA operands must not donate previous URI review payloads.');
}
if (($spans[1]['link_malformed_action_operand_keys'] ?? null) !== ['PA']) {
    throw new RuntimeException('Expected the tailed /PA key to remain visible as malformed action operand review metadata.');
}
if ($visibleText !== 'Clean previous Tailed previous No previous') {
    throw new RuntimeException('Expected visible text to exclude annotation action payloads.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-previous-uri-operand-boundary',
    'native_boundary' => 'Malformed Link annotation /PA previous-action operands are rejected while the primary /A URI remains promotable',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'valid_previous_uri_preserved' => ($spans[0]['link_previous_uri_actions'][0]['uri'] ?? null) === 'https://archive.example.com/clean-previous',
    'tailed_previous_uri_excluded' => !isset($spans[1]['link_previous_uri_actions']) && !str_contains($encodedReview, 'tailed-previous-leak'),
    'primary_tailed_link_promoted' => ($spans[1]['link_uri'] ?? null) === 'https://example.com/current-tailed',
    'malformed_pa_keys' => $spans[1]['link_malformed_action_operand_keys'] ?? [],
    'visible_text_imported' => $visibleText === 'Clean previous Tailed previous No previous',
    'annotation_payload_text_visible' => str_contains($visibleText, 'archive.example.com') || str_contains($visibleText, 'Tailed previous URI review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-previous-uri-operand-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
