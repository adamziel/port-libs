<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect action Hover review Unsupported launch) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 178 718] /Contents (Indirect subtype link review) /A << /S 20 0 R /URI (https://example.com/indirect-action-subtype) /Next << /S 21 0 R /JS (indirectSubtypeScriptReview\\(\\)) >> >> /AA << /E << /S 20 0 R /URI (mailto:indirect-subtype@example.test) >> >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [294 700 418 718] /Contents (Indirect launch subtype review) /A << /S 22 0 R /F (review-helper.exe) >> >>\nendobj\n"
    . "20 0 obj\n/URI\nendobj\n"
    . "21 0 obj\n/JavaScript\nendobj\n"
    . "22 0 obj\n/Launch\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 418.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 418.0, 718.0],
            'spans' => [
                ['text' => 'Indirect action', 'bbox' => [72.0, 700.0, 178.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hover review', 'bbox' => [188.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Unsupported launch', 'bbox' => [294.0, 700.0, 418.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-indirect-action-subtype-boundary',
    'native_boundary' => 'Link annotation action dictionaries resolve indirect /S subtype names before URI span promotion and unsafe action review',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_types' => array_map(
        static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'action_type'),
        $annotations[0]['annotations'] ?? []
    ),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'link_action_types' => array_column($links[0]['links'][0]['actions'] ?? [], 'action_type'),
    'additional_action_uri' => $links[0]['links'][0]['additional_actions'][0]['uri'] ?? null,
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'launch_promoted' => str_contains($encodedLinks, 'review-helper.exe'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Indirect subtype link review')
        || str_contains($plainText, 'Indirect launch subtype review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['promoted_link_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected only the safe indirect /URI subtype link to promote.');
}
if (($summary['link_action_types'] ?? []) !== ['URI', 'JavaScript']) {
    throw new RuntimeException('Expected indirect /S names to resolve for primary and chained action review.');
}
if (($summary['additional_action_uri'] ?? null) !== 'mailto:indirect-subtype@example.test') {
    throw new RuntimeException('Expected indirect /S name to resolve inside additional action review.');
}
if (($summary['launch_promoted'] ?? true) !== false) {
    throw new RuntimeException('Indirect Launch subtype must stay review-only and out of span metadata.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation action payload text must stay out of visible WordPress content.');
}

echo '<!-- markerpdf-pdf-link-annotation-indirect-action-subtype-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
