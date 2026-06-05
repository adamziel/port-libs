<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Direct docs Array spoof Indirect array) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 8 0 R 9 0 R] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 700 150 718] /Contents (Direct docs review) /A << /S /URI /URI (https://example.com/direct-docs-array-boundary) /Next [10 0 R << /S /JavaScript /JS (directFollowupReview\\(\\)) >>] >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Link /Rect [160 700 250 718] /Contents (Array spoof review) /A [<< /S /URI /URI (https://example.com/array-spoof-link) >> << /S /JavaScript /JS (arraySpoofReview\\(\\)) >>] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Link /Rect [260 700 370 718] /Contents (Indirect array review) /A 12 0 R >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/direct-chained-review) >>\nendobj\n"
    . "12 0 obj\n[<< /S /URI /URI (https://example.com/indirect-array-spoof) >> << /S /Launch /F (review-helper.exe) >>]\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 370.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 370.0, 718.0],
            'spans' => [
                ['text' => 'Direct docs', 'bbox' => [72.0, 700.0, 150.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Array spoof', 'bbox' => [160.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Indirect array', 'bbox' => [260.0, 700.0, 370.0, 718.0], 'font' => 'Helvetica'],
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

if ($wordpressText !== '[Direct docs](https://example.com/direct-docs-array-boundary) Array spoof Indirect array') {
    throw new RuntimeException('Expected only the single action dictionary under /A to become a WordPress Markdown link.');
}
if (str_contains($encodedReview, 'array-spoof-link') || str_contains($encodedReview, 'indirect-array-spoof') || str_contains($encodedReview, 'review-helper.exe')) {
    throw new RuntimeException('Malformed primary /A action arrays must stay out of promoted link/review metadata.');
}
if (str_contains($visibleText, 'array-spoof-link') || str_contains($visibleText, 'Array spoof review')) {
    throw new RuntimeException('Annotation action payload text leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-link-primary-action-array-boundary',
    'native_boundary' => 'Link annotation /A must resolve to one action dictionary; top-level action arrays are malformed and are not promoted, while /Next arrays under a valid action remain review metadata',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'primary_action_safeties' => array_map(
        static fn (array $annotation): array => array_column($annotation['actions'] ?? [], 'safety'),
        $annotations[0]['annotations'] ?? []
    ),
    'valid_next_array_preserved' => array_column($links[0]['links'][0]['actions'] ?? [], 'safety') === ['review-uri', 'review-uri', 'blocked-javascript'],
    'direct_array_promoted' => str_contains($encodedReview, 'array-spoof-link'),
    'indirect_array_promoted' => str_contains($encodedReview, 'indirect-array-spoof'),
    'visible_text_imported' => str_contains($visibleText, 'Direct docs Array spoof Indirect array'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Array spoof review') || str_contains($visibleText, 'indirect-array-spoof'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-link-primary-action-array-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
