<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Remote true Remote false Launch blocked Safe docs) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Remote true review) /A << /S /GoToR /F (remote-true.pdf) /D [2 /FitH 720] /NewWindow 20 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 270 718] /Contents (Remote false review) /A << /S /GoToR /F (remote-false.pdf) /D (Remote Appendix) /NewWindow 21 0 R >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [280 700 380 718] /Contents (Launch blocked review) /A << /S /Launch /F (blocked-helper.exe) /NewWindow 20 0 R /Next << /S /URI /URI (https://example.com/launch-followup-review) >> >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [390 700 470 718] /Contents (Safe docs review) /A << /S /URI /URI (https://example.com/safe-docs-new-window-boundary) >> >>\nendobj\n"
    . "20 0 obj\ntrue\nendobj\n"
    . "21 0 obj\nfalse\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 470.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 470.0, 718.0],
            'spans' => [
                ['text' => 'Remote true', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Remote false', 'bbox' => [170.0, 700.0, 270.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Launch blocked', 'bbox' => [280.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Safe docs', 'bbox' => [390.0, 700.0, 470.0, 718.0], 'font' => 'Helvetica'],
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
$annotationRows = $annotations[0]['annotations'] ?? [];
$linkRows = $links[0]['links'] ?? [];
$encodedPromotedRows = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-new-window-operand-boundary',
    'native_boundary' => 'Link annotation GoToR and Launch action /NewWindow booleans are resolved through indirect objects before WordPress review metadata, while Launch remains blocked and non-promoted',
    'annotation_objects' => array_column($annotationRows, 'annotation_object'),
    'annotation_action_safeties' => array_map(static fn (array $row): ?string => $row['actions'][0]['safety'] ?? null, $annotationRows),
    'annotation_new_window_values' => array_map(static fn (array $row): ?bool => $row['actions'][0]['new_window'] ?? null, $annotationRows),
    'promoted_link_objects' => array_column($linkRows, 'annotation_object'),
    'remote_new_window_values' => array_map(static fn (array $row): ?bool => $row['new_window'] ?? null, array_slice($linkRows, 0, 2)),
    'safe_uri_promoted' => str_contains($wordpressText, 'https://example.com/safe-docs-new-window-boundary'),
    'launch_promoted' => str_contains($encodedPromotedRows, 'blocked-helper.exe')
        || str_contains($encodedPromotedRows, 'launch-followup-review'),
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => $visibleText === 'Remote true Remote false Launch blocked Safe docs',
    'annotation_payload_text_visible' => str_contains($visibleText, 'Remote true review')
        || str_contains($visibleText, 'Remote false review')
        || str_contains($visibleText, 'Launch blocked review')
        || str_contains($visibleText, 'Safe docs review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (in_array('--self-test', $argv, true)) {
    if (($summary['annotation_objects'] ?? []) !== [7, 8, 9, 10]) {
        throw new RuntimeException('Expected all page annotations to remain available as review metadata.');
    }
    if (($summary['annotation_new_window_values'] ?? []) !== [true, false, true, null]) {
        throw new RuntimeException('Expected indirect NewWindow booleans to resolve in annotation action review rows.');
    }
    if (($summary['promoted_link_objects'] ?? []) !== [7, 8, 10]) {
        throw new RuntimeException('Expected only remote GoToR and safe URI annotations to promote into link rows.');
    }
    if (($summary['remote_new_window_values'] ?? []) !== [true, false]) {
        throw new RuntimeException('Expected promoted remote link rows to preserve indirect NewWindow booleans.');
    }
    if (($summary['launch_promoted'] ?? true) !== false) {
        throw new RuntimeException('Blocked Launch action or chained URI leaked into promoted WordPress link metadata.');
    }
    if (($summary['wordpress_markdown'] ?? '') !== 'Remote true Remote false Launch blocked [Safe docs](https://example.com/safe-docs-new-window-boundary)') {
        throw new RuntimeException('Expected only the safe URI annotation to become a Markdown link.');
    }
    if (($summary['visible_text_imported'] ?? false) !== true || ($summary['annotation_payload_text_visible'] ?? true) !== false) {
        throw new RuntimeException('Expected visible text isolation for annotation/action operands.');
    }
}

echo '<!-- markerpdf-pdf-link-annotation-new-window-operand-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
