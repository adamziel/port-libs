<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Current docs) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second docs) Tj ET';
$literalDecoyContent = 'BT /F1 12 Tf 72 720 Td (Literal decoy docs) Tj ET';
$dictionaryDecoyContent = 'BT /F1 12 Tf 72 720 Td (Dictionary decoy docs) Tj ET';
$nestedArrayDecoyContent = 'BT /F1 12 Tf 72 720 Td (Nested array decoy docs) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R (8 0 R) << /PrivatePage 9 0 R >> [10 0 R] 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R /Annots [7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 11 0 R /Annots [12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 160 718] /Contents (Current docs review) /A << /S /URI /URI (https://example.com/current-docs) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 13 0 R /Annots [14 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 15 0 R /Annots [16 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Page /Resources << /Font << /F1 5 0 R >> >> /Contents 17 0 R /Annots [18 0 R] >>\nendobj\n"
    . "11 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Second docs review) /A << /S /URI /URI (https://example.com/second-docs) >> >>\nendobj\n"
    . "13 0 obj\n<< /Length " . strlen($literalDecoyContent) . " >>\nstream\n{$literalDecoyContent}\nendstream\nendobj\n"
    . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 200 718] /Contents (Literal decoy review) /A << /S /URI /URI (https://example.com/literal-decoy) >> >>\nendobj\n"
    . "15 0 obj\n<< /Length " . strlen($dictionaryDecoyContent) . " >>\nstream\n{$dictionaryDecoyContent}\nendstream\nendobj\n"
    . "16 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Dictionary decoy review) /A << /S /URI /URI (https://example.com/dictionary-decoy) >> >>\nendobj\n"
    . "17 0 obj\n<< /Length " . strlen($nestedArrayDecoyContent) . " >>\nstream\n{$nestedArrayDecoyContent}\nendstream\nendobj\n"
    . "18 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 235 718] /Contents (Nested array decoy review) /A << /S /URI /URI (https://example.com/nested-array-decoy) >> >>\nendobj\n"
    . "%%EOF";

$pages = [
    [
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 160.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 160.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
    [
        'pnum' => 1,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 150.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 150.0, 718.0],
                'spans' => [
                    ['text' => 'Second docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkPages = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$annotationPages, $linkPages, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($annotationPages, 'page_object') !== [3, 4]) {
    throw new RuntimeException('Expected only top-level page-tree Kids references to produce annotation pages.');
}
if (array_column($linkPages, 'page_object') !== [3, 4]) {
    throw new RuntimeException('Expected only top-level page-tree Kids references to produce link pages.');
}
foreach (['literal-decoy', 'dictionary-decoy', 'nested-array-decoy'] as $decoy) {
    if (str_contains($encodedReview, $decoy) || str_contains((string) ($blocks[0]['text'] ?? ''), $decoy)) {
        throw new RuntimeException('Nested or literal Kids decoy leaked into WordPress links.');
    }
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-annotation-link-kids-token-boundary',
    'native_boundary' => 'page-tree /Kids arrays contribute only direct top-level page references before annotation/link promotion',
    'annotation_page_objects' => array_column($annotationPages, 'page_object'),
    'promoted_link_page_objects' => array_column($linkPages, 'page_object'),
    'promoted_link_objects' => array_map(
        static fn (array $page): array => array_column($page['links'] ?? [], 'annotation_object'),
        $linkPages
    ),
    'promoted_link_uris' => array_map(
        static fn (array $page): array => array_column($page['links'] ?? [], 'uri'),
        $linkPages
    ),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'literal_string_kids_decoy_excluded' => !str_contains($encodedReview, 'literal-decoy'),
    'nested_dictionary_kids_decoy_excluded' => !str_contains($encodedReview, 'dictionary-decoy'),
    'nested_array_kids_decoy_excluded' => !str_contains($encodedReview, 'nested-array-decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-kids-token-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
