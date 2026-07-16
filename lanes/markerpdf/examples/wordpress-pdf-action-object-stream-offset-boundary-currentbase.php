<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Safe action link Malformed action stream) Tj ET';

$decoyPrefix = '<< /Type /Annot /Subtype /Text /Contents (literal action prefix ';
$decoyAction = '<< /S /URI /URI (https://malicious.example.com/object-stream-action) >>';
$decoySuffix = ' literal action suffix) >>';
$decoyMember = $decoyPrefix . $decoyAction . $decoySuffix;
$badOffset = strpos($decoyMember, $decoyAction);
if ($badOffset === false) {
    throw new RuntimeException('Unable to locate object-stream action offset decoy.');
}

$objectStreamHeader = '12 0 8 ' . $badOffset . ' ';
$objectStreamPayload = $objectStreamHeader . $decoyMember . "\n";
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress action offset-boundary object stream.');
}

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [6 0 R 7 0 R] /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$addObject(6, '<< /Type /Annot /Subtype /Link /Rect [72 700 204 718] /Contents (Safe action boundary annotation) /A << /S /URI /URI (https://example.com/safe-action-boundary) >> >>');
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [216 700 430 718] /Contents (Malformed action reference annotation) /A 8 0 R >>');
$addObject(20, '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
$addObject(8, '<< /S /URI /URI (https://stale.example.com/object-stream-action) >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 30; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if ($objectNumber === 8) {
        $rows .= $xrefRow(2, 20, 1);
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
    throw new RuntimeException('Unable to compress action offset-boundary xref stream.');
}

$pdf .= "30 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 430.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 430.0, 718.0],
            'spans' => [
                ['text' => 'Safe action link', 'bbox' => [72.0, 700.0, 204.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Malformed action stream', 'bbox' => [216.0, 700.0, 430.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

if (array_column($links[0]['links'] ?? [], 'annotation_object') !== [6]) {
    throw new RuntimeException('Expected only the safe annotation action to be promoted.');
}
if (($links[0]['links'][0]['uri'] ?? null) !== 'https://example.com/safe-action-boundary') {
    throw new RuntimeException('Expected safe annotation link URI to remain available for WordPress import.');
}
foreach (['malicious.example.com', 'stale.example.com', 'Malformed action reference annotation'] as $hidden) {
    if (str_contains($encodedReview, $hidden) || str_contains($visibleText, $hidden)) {
        throw new RuntimeException('Malformed object-stream action review text leaked: ' . $hidden);
    }
}

$summaryJson = json_encode([
    'support_component' => 'native-pdf-action-object-stream-offset-boundary',
    'native_boundary' => 'xref-stream type-2 action object-stream member offsets must point at top-level object boundaries before WordPress link promotion',
    'link_annotation_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'link_uris' => array_column($links[0]['links'] ?? [], 'uri'),
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'malformed_action_offset_excluded' => !str_contains($encodedReview, 'malicious.example.com')
        && !str_contains($encodedReview, 'Malformed action reference annotation'),
    'stale_direct_action_excluded' => !str_contains($encodedReview, 'stale.example.com'),
    'action_payload_text_excluded_from_visible_text' => !str_contains($visibleText, 'safe-action-boundary')
        && !str_contains($visibleText, 'malicious.example.com'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES);

echo '<!-- markerpdf-pdf-action-object-stream-offset-boundary-currentbase ' . htmlspecialchars($summaryJson ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
