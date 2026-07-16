<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Static docs Coordinate map Chained map) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Static docs URI review) /A << /S /URI /URI (https://example.com/static-docs) /IsMap false >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 260 718] /Contents (Coordinate map URI review) /A << /S /URI /URI (https://maps.example.com/lookup) /IsMap true >> >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [270 700 360 718] /Contents (Chained map review) /A << /S /JavaScript /JS (openMapReview\\(\\)) /Next 10 0 R >> >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://maps.example.com/chained) /IsMap true >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Static docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Coordinate map', 'bbox' => [160.0, 700.0, 260.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Chained map', 'bbox' => [270.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
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

$annotationSafeties = array_map(
    static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
    $annotations[0]['annotations'] ?? []
);
$encodedLinks = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (($blocks[0]['text'] ?? '') !== '[Static docs](https://example.com/static-docs) Coordinate map Chained map') {
    throw new RuntimeException('Expected only the static URI action to be emitted as a WordPress link.');
}
if (str_contains($encodedLinks, 'maps.example.com')) {
    throw new RuntimeException('Coordinate-dependent IsMap URI actions must not be promoted into WordPress links.');
}
if (str_contains($visibleText, 'maps.example.com') || str_contains($visibleText, 'openMapReview')) {
    throw new RuntimeException('IsMap action review payload leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-annotation-ismap-boundary',
    'native_boundary' => 'URI Link annotation /IsMap true actions require activation coordinates and remain review-only before WordPress span promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_action_safety_chains' => $annotationSafeties,
    'annotation_uri_is_map_flags' => array_map(
        static fn (array $annotation): array => array_values(array_filter(
            array_map(static fn (array $action): ?bool => $action['uri_is_map'] ?? null, $annotation['actions'] ?? []),
            static fn (?bool $value): bool => $value !== null
        )),
        $annotations[0]['annotations'] ?? []
    ),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'coordinate_dependent_uri_promoted' => str_contains($encodedLinks, 'maps.example.com'),
    'ismap_review_only' => !str_contains($encodedLinks, 'maps.example.com'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'maps.example.com') && !str_contains($visibleText, 'openMapReview'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

echo '<!-- markerpdf-pdf-link-annotation-ismap-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
