<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Fragment link Chain link Direct link Hidden link Literal decoy Nested decoy) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [6 0 R 10 0 R 12 0 R (14 0 R) [15 0 R]] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "6 0 obj\n[7 0 R 8 0 R 9 0 R]\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 164 718] /Contents (Fragment link review) /A << /S /URI /URI (https://example.com/fragment-link) >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 678 210 696] /Contents (Fragment note review) /T (Import QA) >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /F 2 /Rect [352 700 438 718] /Contents (Hidden fragment review) /A << /S /URI /URI (https://example.com/hidden-fragment-link) >> >>\nendobj\n"
    . "10 0 obj\n11 0 R\nendobj\n"
    . "11 0 obj\n[16 0 R]\nendobj\n"
    . "12 0 obj\n<< /Type /Annot /Subtype /Link /Rect [248 700 342 718] /Contents (Direct link review) /A << /S /URI /URI (https://example.com/direct-link) >> >>\nendobj\n"
    . "14 0 obj\n<< /Type /Annot /Subtype /Link /Rect [448 700 542 718] /Contents (Literal decoy review) /A << /S /URI /URI (https://example.com/literal-decoy-link) >> >>\nendobj\n"
    . "15 0 obj\n<< /Type /Annot /Subtype /Link /Rect [552 700 642 718] /Contents (Nested direct array decoy review) /A << /S /URI /URI (https://example.com/nested-direct-array-decoy) >> >>\nendobj\n"
    . "16 0 obj\n<< /Type /Annot /Subtype /Link /Rect [174 700 238 718] /Contents (Chain link review) /A << /S /URI /URI (https://example.com/chain-link) >> >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 642.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 642.0, 718.0],
            'spans' => [
                ['text' => 'Fragment link', 'bbox' => [72.0, 700.0, 164.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Chain link', 'bbox' => [174.0, 700.0, 238.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Direct link', 'bbox' => [248.0, 700.0, 342.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Hidden link', 'bbox' => [352.0, 700.0, 438.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Literal decoy', 'bbox' => [448.0, 700.0, 542.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Nested decoy', 'bbox' => [552.0, 700.0, 642.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$links = $linkedPages[0]['links'] ?? [];
$spans = $linkedPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-indirect-annots-array-boundary',
    'native_boundary' => 'page /Annots references that resolve to indirect annotation array fragments are flattened before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links, 'annotation_object'),
    'promoted_uris' => array_column($links, 'uri'),
    'hidden_fragment_promoted' => isset($spans[3]['link_uri']),
    'literal_decoy_promoted' => isset($spans[4]['link_uri']),
    'nested_direct_array_decoy_promoted' => isset($spans[5]['link_uri']),
    'wordpress_markdown' => $blocks[0]['text'] ?? '',
    'visible_text_imported' => str_contains($visibleText, 'Fragment link Chain link Direct link Hidden link Literal decoy Nested decoy'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Fragment link review')
        || str_contains($visibleText, 'Fragment note review')
        || str_contains($visibleText, 'Chain link review')
        || str_contains($visibleText, 'Direct link review')
        || str_contains($visibleText, 'literal-decoy-link')
        || str_contains($visibleText, 'nested-direct-array-decoy'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($summary['annotation_objects'] !== [7, 8, 9, 16, 12]) {
    throw new RuntimeException('Expected indirect Annots array fragments to be flattened into annotation review rows.');
}
if ($summary['promoted_link_objects'] !== [7, 16, 12]) {
    throw new RuntimeException('Expected only visible link annotations from indirect fragments and direct Annots entries to promote.');
}
if ($summary['hidden_fragment_promoted'] || $summary['literal_decoy_promoted'] || $summary['nested_direct_array_decoy_promoted']) {
    throw new RuntimeException('Expected hidden, literal, and direct nested-array decoys to stay out of WordPress link promotion.');
}
if ($summary['annotation_payload_text_visible']) {
    throw new RuntimeException('Expected annotation review payloads and decoy URI operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-pdf-link-indirect-annots-array-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
