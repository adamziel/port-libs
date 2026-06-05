<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Relative guide Query only Fragment only Absolute ftp) Tj ET';
$base = 'https://example.com/imports/2026/base.pdf?keep=1';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /URI << /Base ({$base}) >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R 10 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 158 718] /Contents (Relative guide review) /A << /S /URI /URI (docs/../guides/import.html?source=pdf#section) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [168 700 252 718] /Contents (Query only review) /A << /S /URI /URI (?download=1) >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [262 700 356 718] /Contents (Fragment only review) /A << /S /URI /URI (#fragment-only) >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Annot /Subtype /Link /Rect [366 700 456 718] /Contents (Absolute ftp review) /A << /S /URI /URI (ftp://files.example.com/archive.zip) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 456.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 456.0, 718.0],
            'spans' => [
                ['text' => 'Relative guide', 'bbox' => [72.0, 700.0, 158.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Query only', 'bbox' => [168.0, 700.0, 252.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Fragment only', 'bbox' => [262.0, 700.0, 356.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Absolute ftp', 'bbox' => [366.0, 700.0, 456.0, 718.0], 'font' => 'Helvetica'],
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
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];

$annotationActionMetadata = array_map(
    static fn (array $annotation): array => [
        'object' => $annotation['annotation_object'] ?? null,
        'safety' => $annotation['actions'][0]['safety'] ?? null,
        'uri' => $annotation['actions'][0]['uri'] ?? null,
        'raw_uri' => $annotation['actions'][0]['raw_uri'] ?? null,
        'uri_base' => $annotation['actions'][0]['uri_base'] ?? null,
        'uri_relative' => (bool) ($annotation['actions'][0]['uri_relative'] ?? false),
        'uri_resolved_from_base' => (bool) ($annotation['actions'][0]['uri_resolved_from_base'] ?? false),
    ],
    $annotations[0]['annotations'] ?? []
);

$summary = [
    'support_component' => 'native-pdf-link-annotation-uri-base-boundary',
    'native_boundary' => 'Catalog /URI /Base resolves relative Link annotation URI actions before WordPress href promotion while raw URI/base review metadata stays on the promoted span',
    'catalog_uri_base' => $base,
    'annotation_action_metadata' => $annotationActionMetadata,
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'span_raw_uris' => array_map(static fn (array $span): ?string => $span['link_raw_uri'] ?? null, $spans),
    'span_uri_bases' => array_map(static fn (array $span): ?string => $span['link_uri_base'] ?? null, $spans),
    'span_resolved_from_base' => array_map(static fn (array $span): bool => (bool) ($span['link_uri_resolved_from_base'] ?? false), $spans),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($plainText, 'Relative guide Query only Fragment only Absolute ftp'),
    'annotation_payload_text_visible' => str_contains($plainText, 'Relative guide review')
        || str_contains($plainText, 'Query only review')
        || str_contains($plainText, 'Fragment only review')
        || str_contains($plainText, 'Absolute ftp review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($spans[0]['link_uri'] ?? null) !== 'https://example.com/imports/2026/guides/import.html?source=pdf#section'
    || ($spans[0]['link_raw_uri'] ?? null) !== 'docs/../guides/import.html?source=pdf#section'
    || ($spans[0]['link_uri_base'] ?? null) !== $base
    || ($spans[1]['link_uri'] ?? null) !== 'https://example.com/imports/2026/base.pdf?download=1'
    || ($spans[2]['link_uri'] ?? null) !== 'https://example.com/imports/2026/base.pdf?keep=1#fragment-only'
    || ($spans[3]['link_uri'] ?? null) !== 'ftp://files.example.com/archive.zip'
    || ($summary['promoted_link_objects'] ?? []) !== [7, 8, 9, 10]
    || ($summary['annotation_payload_text_visible'] ?? null) !== false
) {
    throw new RuntimeException('Unexpected markerPDF URI Base link annotation boundary smoke output.');
}

echo '<!-- markerpdf-pdf-link-annotation-uri-base-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
