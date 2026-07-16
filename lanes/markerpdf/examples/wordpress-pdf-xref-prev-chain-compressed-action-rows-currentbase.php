<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousObjects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 240] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>',
    4 => "<< /Length 68 >>\nstream\nBT /F1 12 Tf 48 190 Td (Previous compressed action docs) Tj ET\nendstream",
    5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    7 => '<< /Type /Annot /Subtype /Link /Rect [48 180 210 202] /A 8 0 R /AA << /E 9 0 R >> >>',
    8 => '<< /S /URI /URI (https://example.com/stale-prev-compressed-action) >>',
    9 => "<< /S /JavaScript /JS (app.alert('stale-prev-compressed-action')) >>",
];

$pdf = "%PDF-1.7\n";
$previousOffsets = [];
foreach ($previousObjects as $objectNumber => $body) {
    $previousOffsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
}

$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n0 10\n" . $xrefRow(0, 65535, 'f');
for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
    $pdf .= isset($previousOffsets[$objectNumber])
        ? $xrefRow($previousOffsets[$objectNumber])
        : $xrefRow(0, 0, 'f');
}
$pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$previousXrefOffset}\n%%EOF\n";

$currentUri = 'https://example.com/current-compressed-prev-action';
$currentAdditionalUri = 'mailto:current-compressed-prev-action@example.test';
$objectStreamMembers = [
    8 => "<< /S /URI /URI ({$currentUri}) >>",
    9 => "<< /S /URI /URI ({$currentAdditionalUri}) >>",
];
$objectStreamHeader = '';
$objectStreamPayload = '';
foreach ($objectStreamMembers as $objectNumber => $body) {
    $objectStreamHeader .= "{$objectNumber} " . strlen($objectStreamPayload) . ' ';
    $objectStreamPayload .= $body . "\n";
}
$objectStreamBytes = $objectStreamHeader . $objectStreamPayload;

$currentObjects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 320 240] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R] >>',
    4 => "<< /Length 67 >>\nstream\nBT /F1 12 Tf 48 190 Td (Current compressed action docs) Tj ET\nendstream",
    5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    7 => '<< /Type /Annot /Subtype /Link /Rect [48 180 210 202] /Contents (Current compressed action docs) /A 8 0 R /AA << /E 9 0 R >> >>',
    20 => '<< /Type /ObjStm /N 2 /First ' . strlen($objectStreamHeader) . ' /Length ' . strlen($objectStreamBytes) . " >>\nstream\n{$objectStreamBytes}\nendstream",
];

$currentOffsets = [];
foreach ($currentObjects as $objectNumber => $body) {
    $currentOffsets[$objectNumber] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
}

$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);
$xrefRows = '';
foreach ([1, 2, 3, 4, 5, 7, 20] as $objectNumber) {
    $xrefRows .= $xrefStreamRow(1, $currentOffsets[$objectNumber], 0);
}

$xrefStreamOffset = strlen($pdf);
$pdf .= "21 0 obj\n"
    . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /W [1 4 1] /Index [1 5 7 1 20 1] /Length ' . strlen($xrefRows) . " >>\n"
    . "stream\n{$xrefRows}\nendstream\nendobj\n"
    . "startxref\n{$xrefStreamOffset}\n%%EOF\n";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [48.0, 180.0, 220.0, 202.0],
        'lines' => [[
            'bbox' => [48.0, 180.0, 220.0, 202.0],
            'spans' => [[
                'text' => 'Current compressed action docs',
                'bbox' => [48.0, 180.0, 220.0, 202.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$summary = [
    'support_component' => 'native-pdf-xref-prev-chain-compressed-action-rows',
    'native_boundary' => 'xref-stream Prev incremental update repairs omitted current compressed action rows before stale action inheritance in WordPress link promotion',
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $xrefStreamOffset,
    'current_uri_promoted' => str_contains($wordpressText, $currentUri),
    'current_additional_action_reviewed' => str_contains($encodedReview, $currentAdditionalUri),
    'omitted_compressed_action_rows_repaired' => str_contains($pdf, '/Index [1 5 7 1 20 1]')
        && str_contains($encodedReview, $currentUri)
        && str_contains($encodedReview, $currentAdditionalUri),
    'stale_prev_action_excluded' => !str_contains($encodedReview, 'stale-prev-compressed-action'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $wordpressText !== '[Current compressed action docs](https://example.com/current-compressed-prev-action)'
    || $summary['current_additional_action_reviewed'] !== true
    || $summary['omitted_compressed_action_rows_repaired'] !== true
    || $summary['stale_prev_action_excluded'] !== true
) {
    throw new RuntimeException('Expected current compressed action objects to win before WordPress link promotion.');
}

echo '<!-- markerpdf-xref-prev-chain-compressed-action-rows-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
