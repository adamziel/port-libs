<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect object-stream link Stale indirect link) Tj ET';

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

$compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 240 718] /Contents (Indirect object-stream annotation) /T (Indirect reviewer) /NM (indirect-object-stream-link) /A << /S /URI /URI (https://example.com/indirect-object-stream-link) >> >>';
$objectStreamHeader = '7 0 ';
$objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress indirect operand annotation object stream.');
}

$addObject(20, '<< /Type /ObjStm /N 32 0 R /First 33 0 R /Filter 31 0 R /Length 30 0 R >>' . "\nstream\n{$objectStream}\nendstream");
$addObject(30, (string) strlen($objectStream));
$addObject(31, '/FlateDecode');
$addObject(32, '1');
$addObject(33, (string) strlen($objectStreamHeader));
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [246 700 374 718] /Contents (Stale direct indirect annotation) /T (Stale indirect reviewer) /NM (stale-indirect-link) /A << /S /URI /URI (https://stale.example.com/indirect-object-stream-link) >> >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if ($objectNumber === 7) {
        $rows .= $xrefRow(2, 20, 0);
        continue;
    }
    if ($objectNumber === 40) {
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
    throw new RuntimeException('Unable to compress indirect operand annotation xref stream.');
}

$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 374.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 374.0, 718.0],
            'spans' => [
                ['text' => 'Indirect object-stream link', 'bbox' => [72.0, 700.0, 240.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale indirect link', 'bbox' => [246.0, 700.0, 374.0, 718.0], 'font' => 'Helvetica'],
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
    throw new RuntimeException('Expected compressed indirect-operand annotation object to own review metadata.');
}
if (($links[0]['links'][0]['uri'] ?? null) !== 'https://example.com/indirect-object-stream-link') {
    throw new RuntimeException('Expected compressed indirect-operand URI to be promoted to a WordPress link.');
}
if (($blocks[0]['text'] ?? '') !== '[Indirect object-stream link](https://example.com/indirect-object-stream-link) Stale indirect link') {
    throw new RuntimeException('Expected only the compressed annotation to decorate the supplied WordPress text span.');
}
foreach (['stale.example.com', 'Stale direct indirect annotation', 'Stale indirect reviewer', 'stale-indirect-link'] as $hidden) {
    if (str_contains($encodedReview, $hidden)) {
        throw new RuntimeException('Stale direct annotation review text leaked: ' . $hidden);
    }
}
foreach (['indirect-object-stream-link', 'stale.example.com', 'Indirect object-stream annotation', 'Stale direct indirect annotation'] as $reviewOnlyText) {
    if (str_contains($visibleText, $reviewOnlyText)) {
        throw new RuntimeException('Review-only annotation text leaked into visible text: ' . $reviewOnlyText);
    }
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-object-stream-xref-link-annotation-indirect-operands',
    'native_boundary' => 'xref-stream type-2 Link annotations resolve selected indirect object-stream /Length, /Filter, /N, and /First operands before WordPress link promotion',
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'stale_direct_annotation_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'Stale direct indirect annotation'),
    'annotation_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'Indirect object-stream annotation')
        && !str_contains($visibleText, 'stale.example.com'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-link-annotation-object-stream-indirect-operands-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
