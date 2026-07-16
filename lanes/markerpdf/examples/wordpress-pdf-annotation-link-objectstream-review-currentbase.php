<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Compressed review link Stale direct review) Tj ET';

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

$compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 222 718] /Contents (Compressed review annotation) /T (Current reviewer) /NM (compressed-review-link) /A << /S /URI /URI (https://example.com/current-compressed-review) >> >>';
$objectStreamHeader = '7 0 ';
$objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress annotation review object-stream fixture.');
}
$addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [238 700 380 718] /Contents (Stale direct annotation review) /T (Stale reviewer) /NM (stale-direct-review-link) /A << /S /URI /URI (https://stale.example.com/direct-review) >> >>');

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
    throw new RuntimeException('Unable to compress annotation review xref stream.');
}

$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 380.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 380.0, 718.0],
            'spans' => [
                ['text' => 'Compressed review link', 'bbox' => [72.0, 700.0, 222.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale direct review', 'bbox' => [238.0, 700.0, 380.0, 718.0], 'font' => 'Helvetica'],
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

if (($annotations[0]['annotations'][0]['contents'] ?? null) !== 'Compressed review annotation') {
    throw new RuntimeException('Expected compressed annotation body to drive annotation review.');
}
if (($links[0]['links'][0]['uri'] ?? null) !== 'https://example.com/current-compressed-review') {
    throw new RuntimeException('Expected compressed annotation body to drive link promotion.');
}
if (str_contains($encodedReview, 'stale.example.com') || str_contains($encodedReview, 'Stale direct annotation review')) {
    throw new RuntimeException('Stale direct annotation body leaked into review metadata.');
}
if (str_contains($visibleText, 'current-compressed-review') || str_contains($visibleText, 'Compressed review annotation')) {
    throw new RuntimeException('Annotation action or review text leaked into visible PDF text.');
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-annotation-link-object-stream-review-boundary',
    'native_boundary' => 'xref-stream type-2 object-stream Link annotation bodies are selected before stale direct annotation bodies for review metadata and WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'annotation_contents' => array_column($annotations[0]['annotations'] ?? [], 'contents'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'stale_direct_annotation_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'Stale direct annotation review'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'current-compressed-review')
        && !str_contains($visibleText, 'Compressed review annotation'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-annotation-link-objectstream-review-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
