<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Commented object stream link Stale direct comment link) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');

$compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 242 718] /Contents (Commented object-stream annotation) /T (Header comment reviewer) /NM (commented-object-stream-link) /A << /S /URI /URI (https://example.com/commented-object-stream-link) >> >>';
$objectStreamHeader = "% ignored annotation reviewer digits 7 9999\r\n7 0 ";
$objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress commented annotation object stream.');
}
$addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [258 700 420 718] /Contents (Stale direct header comment annotation) /T (Stale comment reviewer) /NM (stale-comment-link) /A << /S /URI /URI (https://stale.example.com/comment-header) >> >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if ($objectNumber === 7) {
        $rows .= $xrefRow(2, 20, 0);
        continue;
    }
    if ($objectNumber === 30) {
        $rows .= $xrefRow(1, $xrefOffset);
        continue;
    }
    if (isset($offsets[$objectNumber])) {
        $rows .= $xrefRow(1, $offsets[$objectNumber]);
        continue;
    }

    $rows .= $xrefRow(0, 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress commented annotation xref stream.');
}

$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 420.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 420.0, 718.0],
            'spans' => [
                ['text' => 'Commented object stream link', 'bbox' => [72.0, 700.0, 242.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale direct comment link', 'bbox' => [258.0, 700.0, 420.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationExtractor = new PdfAnnotationExtractor();
$linkExtractor = new PdfLinkAnnotationExtractor();
$annotations = $annotationExtractor->extractPageAnnotations($pdf);
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($annotations[0]['annotations'] ?? [], 'annotation_object') !== [7]) {
    throw new RuntimeException('Expected commented object-stream annotation header to preserve the xref-selected Link annotation.');
}
if (($links[0]['links'][0]['uri'] ?? null) !== 'https://example.com/commented-object-stream-link') {
    throw new RuntimeException('Expected compressed commented object-stream Link annotation to be promoted.');
}
foreach (['stale.example.com', 'Stale direct header comment annotation', 'Stale comment reviewer'] as $hidden) {
    if (str_contains($encodedReview, $hidden) || str_contains($visibleText, $hidden)) {
        throw new RuntimeException('Stale annotation review text leaked: ' . $hidden);
    }
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-annotation-object-stream-header-comments',
    'native_boundary' => 'xref-stream type-2 annotation object-stream headers treat PDF comments as whitespace before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'commented_header_link_promoted' => ($links[0]['links'][0]['uri'] ?? null) === 'https://example.com/commented-object-stream-link',
    'stale_direct_annotation_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'Stale direct header comment annotation'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'commented-object-stream-link')
        && !str_contains($visibleText, 'Stale direct header comment annotation'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-object-stream-header-comment-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
