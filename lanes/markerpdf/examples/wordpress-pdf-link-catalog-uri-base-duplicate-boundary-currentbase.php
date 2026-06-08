<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Relative docs Fragment docs Absolute docs Unsafe docs) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base (https://docs.example.com/import/current/guide.pdf) /Base (https://evil.example.com/rewrite/base.pdf) >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Relative duplicate-base review) /A << /S /URI /URI (articles/import.html#setup) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [170 700 258 718] /Contents (Fragment duplicate-base review) /A << /S /URI /URI (#field-reference) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [268 700 360 718] /Contents (Absolute duplicate-base review) /A << /S /URI /URI (https://cdn.example.com/absolute.pdf) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [370 700 456 718] /Contents (Unsafe duplicate-base review) /A << /S /URI /URI (javascript:duplicateBase\\(\\)) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 456.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'spans' => [
                ['text' => 'Relative docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Fragment docs', 'bbox' => [170.0, 700.0, 258.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Absolute docs', 'bbox' => [268.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Unsafe docs', 'bbox' => [370.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
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
$promotedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$promotedLinks = $links[0]['links'] ?? [];

$summary = [
    'support_component' => 'native-pdf-link-catalog-uri-base-duplicate-boundary',
    'native_boundary' => 'duplicate Catalog /URI /Base keys are ignored before relative Link annotation href resolution',
    'annotation_action_safeties' => array_map(
        static fn (array $annotation): ?string => $annotation['actions'][0]['safety'] ?? null,
        $annotations[0]['annotations'] ?? []
    ),
    'promoted_link_objects' => array_column($promotedLinks, 'annotation_object'),
    'relative_href' => $promotedLinks[0]['uri'] ?? null,
    'relative_resolved_from_base' => $promotedLinks[0]['uri_resolved_from_base'] ?? null,
    'relative_uri_base_present' => array_key_exists('uri_base', $promotedLinks[0] ?? []),
    'fragment_href' => $promotedLinks[1]['uri'] ?? null,
    'fragment_resolved_from_base' => $promotedLinks[1]['uri_resolved_from_base'] ?? null,
    'fragment_uri_base_present' => array_key_exists('uri_base', $promotedLinks[1] ?? []),
    'absolute_href' => $promotedLinks[2]['uri'] ?? null,
    'span_hrefs' => array_values(array_filter(array_map(
        static fn (array $span): ?string => is_string($span['link_uri'] ?? null) ? $span['link_uri'] : null,
        $spans
    ))),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'duplicate_bases_promoted' => str_contains($promotedReview, 'docs.example.com')
        || str_contains($promotedReview, 'evil.example.com'),
    'unsafe_uri_promoted' => str_contains($promotedReview, 'javascript:duplicateBase'),
    'visible_text_imported' => str_contains($visibleText, 'Relative docs Fragment docs Absolute docs Unsafe docs'),
    'visible_text_excludes_link_metadata' => !str_contains($visibleText, 'articles/import.html')
        && !str_contains($visibleText, 'field-reference')
        && !str_contains($visibleText, 'cdn.example.com')
        && !str_contains($visibleText, 'docs.example.com')
        && !str_contains($visibleText, 'evil.example.com')
        && !str_contains($visibleText, 'duplicateBase')
        && !str_contains($visibleText, 'Relative duplicate-base review')
        && !str_contains($visibleText, 'Fragment duplicate-base review')
        && !str_contains($visibleText, 'Absolute duplicate-base review'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['annotation_action_safeties'] !== ['review-uri', 'review-uri', 'review-uri', 'blocked-unsafe-uri']
    || $summary['promoted_link_objects'] !== [7, 8, 9]
    || $summary['relative_href'] !== 'articles/import.html#setup'
    || $summary['relative_resolved_from_base'] !== false
    || $summary['relative_uri_base_present'] !== false
    || $summary['fragment_href'] !== '#field-reference'
    || $summary['fragment_resolved_from_base'] !== false
    || $summary['fragment_uri_base_present'] !== false
    || $summary['absolute_href'] !== 'https://cdn.example.com/absolute.pdf'
    || $summary['duplicate_bases_promoted'] !== false
    || $summary['unsafe_uri_promoted'] !== false
    || $summary['visible_text_excludes_link_metadata'] !== true
) {
    throw new RuntimeException('Unexpected markerPDF duplicate catalog URI Base boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-catalog-uri-base-duplicate-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($spans as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (isset($span['link_uri'])) {
        $href = htmlspecialchars((string) $span['link_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<a href="' . $href . '">' . $text . '</a>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
