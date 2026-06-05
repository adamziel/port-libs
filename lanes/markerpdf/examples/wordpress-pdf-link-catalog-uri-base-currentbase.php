<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Relative docs Absolute docs Unsafe docs Fragment docs) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI 12 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Relative base link review) /A << /S /URI /URI (articles/plugin-guide.pdf?from=pdf#setup) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 244 718] /Contents (Absolute link review) /A << /S /URI /URI (https://cdn.example.com/absolute.pdf) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [254 700 332 718] /Contents (Unsafe JavaScript link review) /A << /S /URI /URI (javascript:relativeBase\\(\\)) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [342 700 430 718] /Contents (Fragment base link review) /A << /S /URI /URI (#field-reference) >> >>\nendobj\n"
    . "12 0 obj\n<< /Base (https://docs.example.com/import/current/guide.pdf) >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 430.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'spans' => [
                ['text' => 'Relative docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Absolute docs', 'bbox' => [160.0, 700.0, 244.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Unsafe docs', 'bbox' => [254.0, 700.0, 332.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Fragment docs', 'bbox' => [342.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$relativeUri = 'https://docs.example.com/import/current/articles/plugin-guide.pdf?from=pdf#setup';
$fragmentUri = 'https://docs.example.com/import/current/guide.pdf#field-reference';
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$annotationSafeties = array_map(
    static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
    $annotations[0]['annotations'] ?? []
);
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$summary = [
    'support_component' => 'native-pdf-link-catalog-uri-base-boundary',
    'native_boundary' => 'Catalog /URI /Base resolves relative Link annotation URI actions before WordPress Markdown link promotion',
    'annotation_action_safeties' => $annotationSafeties,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'relative_raw_uri' => $links[0]['links'][0]['raw_uri'] ?? null,
    'relative_uri_base' => $links[0]['links'][0]['uri_base'] ?? null,
    'relative_resolved_href' => $links[0]['links'][0]['uri'] ?? null,
    'absolute_href' => $links[0]['links'][1]['uri'] ?? null,
    'fragment_resolved_href' => $links[0]['links'][2]['uri'] ?? null,
    'unsafe_uri_promoted' => str_contains($encodedReview, 'javascript:relativeBase')
        || str_contains($encodedReview, 'Unsafe JavaScript link review'),
    'span_hrefs' => array_values(array_filter(array_map(
        static fn (array $span): ?string => is_string($span['link_uri'] ?? null) ? $span['link_uri'] : null,
        $spans
    ))),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Relative docs Absolute docs Unsafe docs Fragment docs'),
    'visible_text_excludes_link_metadata' => !str_contains($visibleText, 'plugin-guide.pdf')
        && !str_contains($visibleText, 'cdn.example.com')
        && !str_contains($visibleText, 'relativeBase')
        && !str_contains($visibleText, 'field-reference')
        && !str_contains($visibleText, 'Relative base link review')
        && !str_contains($visibleText, 'Unsafe JavaScript link review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['annotation_action_safeties'] !== ['review-uri', 'review-uri', 'blocked-unsafe-uri', 'review-uri']
    || $summary['promoted_link_objects'] !== [7, 8, 10]
    || $summary['relative_resolved_href'] !== $relativeUri
    || $summary['fragment_resolved_href'] !== $fragmentUri
    || $summary['unsafe_uri_promoted'] !== false
    || $summary['visible_text_excludes_link_metadata'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF catalog URI base link boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-catalog-uri-base-boundary ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $raw = htmlspecialchars((string) ($span['link_actions_review'][0]['raw_uri'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '" data-markerpdf-link-raw-uri="' . $raw . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
