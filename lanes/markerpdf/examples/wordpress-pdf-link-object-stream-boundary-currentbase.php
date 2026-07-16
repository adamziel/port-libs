<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Compressed link Stale direct link) Tj ET';

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

$compressedAnnotation = '<< /Type /Annot /Subtype /Link /Rect [72 700 190 718] /Contents (Compressed annotation review) /A << /S /URI /URI (https://example.com/current-compressed-link) >> >>';
$objectStreamHeader = '7 0 ';
$objectStreamPayload = $objectStreamHeader . $compressedAnnotation . "\n";
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress link-annotation object stream fixture.');
}
$addObject(20, '<< /Type /ObjStm /N 1 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [204 700 330 718] /Contents (Stale direct annotation review) /A << /S /URI /URI (https://stale.example.com/direct-link) >> >>');

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
    throw new RuntimeException('Unable to compress link-annotation xref stream fixture.');
}

$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 330.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 330.0, 718.0],
            'spans' => [
                ['text' => 'Compressed link', 'bbox' => [72.0, 700.0, 190.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale direct link', 'bbox' => [204.0, 700.0, 330.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfLinkAnnotationExtractor();
$links = $extractor->extractPageLinks($pdf);
$linkedPages = $extractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'support_component' => 'native-pdf-link-annotation-object-stream-boundary',
    'native_boundary' => 'xref-stream type-2 object-stream Link annotation bodies override stale direct same-number annotation bodies before WordPress span promotion',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'current_compressed_link_promoted' => str_contains($blocks[0]['text'] ?? '', '[Compressed link](https://example.com/current-compressed-link)'),
    'stale_direct_link_excluded' => !str_contains($encodedReview, 'stale.example.com') && !str_contains($encodedReview, 'Stale direct annotation review'),
    'stale_span_unlinked' => !isset($linkedPages[0]['blocks'][0]['lines'][0]['spans'][1]['link_uri']),
    'visible_text_excludes_annotation_payloads' => !str_contains($plainText, 'current-compressed-link') && !str_contains($plainText, 'Compressed annotation review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['promoted_link_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected object-stream annotation object 7 to be promoted exactly once.');
}
if ($summary['current_compressed_link_promoted'] !== true || $summary['stale_direct_link_excluded'] !== true || $summary['stale_span_unlinked'] !== true) {
    throw new RuntimeException('Expected compressed annotation promotion with stale direct annotation exclusion.');
}
if ($summary['visible_text_excludes_annotation_payloads'] !== true) {
    throw new RuntimeException('Annotation object-stream payload leaked into visible WordPress text.');
}

echo '<!-- markerpdf-pdf-link-object-stream-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
