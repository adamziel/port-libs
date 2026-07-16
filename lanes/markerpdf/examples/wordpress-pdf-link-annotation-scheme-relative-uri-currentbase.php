<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Safe docs Scheme docs Relative docs) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base (https://docs.example.com/import/base.pdf) >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Safe absolute review) /A << /S /URI /URI (https://example.com/safe-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Scheme relative review) /A << /S /URI /URI (//evil.example/protocol-relative.pdf) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 360 718] /Contents (Relative guide review) /A << /S /URI /URI (guide.html#setup) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Safe docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Scheme docs', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Relative docs', 'bbox' => [260.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
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
$encodedBlocks = json_encode($blocks, JSON_UNESCAPED_SLASHES) ?: '';
$annotationSafeties = array_map(
    static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-annotation-scheme-relative-uri-boundary',
    'native_boundary' => 'Link annotation URI actions beginning with // stay review-only before WordPress Markdown link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safeties' => $annotationSafeties,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'scheme_relative_uri_blocked' => ($annotationSafeties[1] ?? null) === 'blocked-unsafe-uri',
    'scheme_relative_uri_promoted' => str_contains($encodedBlocks, '//evil.example'),
    'safe_absolute_promoted' => str_contains($wordpressText, 'https://example.com/safe-docs'),
    'relative_uri_resolved_from_base' => str_contains($wordpressText, 'https://docs.example.com/import/guide.html'),
    'wordpress_markdown' => $wordpressText,
    'visible_text_imported' => str_contains($visibleText, 'Safe docs Scheme docs Relative docs'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Safe absolute review')
        || str_contains($visibleText, 'Scheme relative review')
        || str_contains($visibleText, 'Relative guide review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$summary['scheme_relative_uri_blocked'] || $summary['scheme_relative_uri_promoted']) {
    throw new RuntimeException('Expected scheme-relative Link annotation URI to stay review-only.');
}
if (!$summary['safe_absolute_promoted'] || !$summary['relative_uri_resolved_from_base']) {
    throw new RuntimeException('Expected safe absolute and ordinary base-relative URI links to remain promoted.');
}
if ($summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Annotation review payload text leaked into visible WordPress content.');
}

echo '<!-- markerpdf-pdf-link-annotation-scheme-relative-uri-currentbase '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
