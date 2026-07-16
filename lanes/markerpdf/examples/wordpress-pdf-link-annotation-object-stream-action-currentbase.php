<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Compressed action link Stale action decoy) Tj ET';

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

$compressedObjects = [
    7 => '<< /Type /Annot /Subtype /Link /Rect [72 700 220 718] /Contents (Compressed action review) /A 30 0 R /AA << /E 31 0 R >> /PA 32 0 R >>',
    30 => '<< /S /URI /URI (https://example.com/current-compressed-action) /Next << /S /JavaScript /JS (currentFollowupReview\(\)) >> >>',
    31 => '<< /S /URI /URI (mailto:compressed-action@example.test) >>',
    32 => '<< /S /URI /URI (https://archive.example.com/current-previous-action) >>',
];
$headerParts = [];
$payload = '';
foreach ($compressedObjects as $objectNumber => $body) {
    $headerParts[] = (string) $objectNumber;
    $headerParts[] = (string) strlen($payload);
    $payload .= $body . "\n";
}
$objectStreamHeader = implode(' ', $headerParts) . ' ';
$objectStreamPayload = $objectStreamHeader . $payload;
$objectStream = gzcompress($objectStreamPayload);
if (!is_string($objectStream)) {
    throw new RuntimeException('Unable to compress link-action object stream fixture.');
}
$addObject(20, '<< /Type /ObjStm /N ' . count($compressedObjects) . ' /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [236 700 360 718] /Contents (Stale direct annotation review) /A 30 0 R >>');
$addObject(30, '<< /S /URI /URI (https://stale.example.com/direct-action) /Next 31 0 R >>');
$addObject(31, '<< /S /Launch /F (stale-action-helper.exe) >>');
$addObject(32, '<< /S /URI /URI (https://archive.example.com/stale-previous-action) >>');

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber <= 40; $objectNumber++) {
    if ($objectNumber === 0) {
        $rows .= $xrefRow(0, 0, 255);
        continue;
    }
    if (array_key_exists($objectNumber, $compressedObjects)) {
        $rows .= $xrefRow(2, 20, array_search($objectNumber, array_keys($compressedObjects), true));
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
    throw new RuntimeException('Unable to compress link-action xref stream fixture.');
}

$pdf .= "40 0 obj\n"
    . '<< /Type /XRef /Size 41 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 360.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 360.0, 718.0],
            'spans' => [
                ['text' => 'Compressed action link', 'bbox' => [72.0, 700.0, 220.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Stale action decoy', 'bbox' => [236.0, 700.0, 360.0, 718.0], 'font' => 'Helvetica'],
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
$link = $links[0]['links'][0] ?? [];

$summary = [
    'support_component' => 'native-pdf-link-annotation-object-stream-action-boundary',
    'native_boundary' => 'xref-stream type-2 object-stream action bodies override stale direct same-number action bodies before WordPress span promotion and review',
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'promoted_uri' => $link['uri'] ?? null,
    'action_types' => array_column($link['actions'] ?? [], 'action_type'),
    'action_safeties' => array_column($link['actions'] ?? [], 'safety'),
    'additional_action_uri' => $link['additional_actions'][0]['uri'] ?? null,
    'previous_action_uri' => $link['previous_uri_actions'][0]['uri'] ?? null,
    'wordpress_text' => $blocks[0]['text'] ?? null,
    'stale_direct_action_excluded' => !str_contains($encodedReview, 'stale.example.com')
        && !str_contains($encodedReview, 'stale-action-helper.exe')
        && !str_contains($encodedReview, 'stale-previous-action')
        && !str_contains($encodedReview, 'Stale direct annotation review'),
    'visible_text_excludes_action_payloads' => !str_contains($plainText, 'current-compressed-action')
        && !str_contains($plainText, 'compressed-action@example.test')
        && !str_contains($plainText, 'current-previous-action')
        && !str_contains($plainText, 'Compressed action review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (($summary['promoted_link_objects'] ?? []) !== [7]) {
    throw new RuntimeException('Expected object-stream annotation object 7 to promote exactly once.');
}
if (($summary['promoted_uri'] ?? null) !== 'https://example.com/current-compressed-action') {
    throw new RuntimeException('Expected current compressed action URI to promote before stale direct action URI.');
}
if (($summary['action_types'] ?? []) !== ['URI', 'JavaScript'] || ($summary['action_safeties'] ?? []) !== ['review-uri', 'blocked-javascript']) {
    throw new RuntimeException('Expected current compressed chained action review metadata.');
}
if (($summary['additional_action_uri'] ?? null) !== 'mailto:compressed-action@example.test') {
    throw new RuntimeException('Expected current compressed additional action URI.');
}
if (($summary['previous_action_uri'] ?? null) !== 'https://archive.example.com/current-previous-action') {
    throw new RuntimeException('Expected current compressed previous action URI.');
}
if (($summary['stale_direct_action_excluded'] ?? false) !== true || ($summary['visible_text_excludes_action_payloads'] ?? false) !== true) {
    throw new RuntimeException('Expected stale direct action data and review-only payloads to stay out of WordPress output.');
}

echo '<!-- markerpdf-pdf-link-annotation-object-stream-action-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars((string) ($blocks[0]['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
