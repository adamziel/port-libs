<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev action link) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev action docs) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Indirect Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-indirect-prev-action) >>');
$addObject(9, 0, '<< /S /JavaScript /JS (staleIndirectPrevHover()) >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($offsets['1:0'])
    . $xrefTableRow($offsets['2:0'])
    . $xrefTableRow($offsets['3:0'])
    . $xrefTableRow($offsets['4:0'])
    . $xrefTableRow($offsets['5:0'])
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($offsets['7:0'])
    . $xrefTableRow($offsets['8:0'])
    . $xrefTableRow($offsets['9:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";
$previousOffsets = $offsets;

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Indirect Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://example.com/current-indirect-prev-action) >>');
$addObject(9, 0, '<< /S /URI /URI (mailto:current-indirect-prev-action@example.test) >>');
$prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

$rows = ''
    . $xrefStreamRow(1, $offsets['1:0'], 0)
    . $xrefStreamRow(1, $offsets['2:0'], 0)
    . $xrefStreamRow(1, $offsets['3:0'], 0)
    . $xrefStreamRow(1, $offsets['4:0'], 0)
    . $xrefStreamRow(1, $previousOffsets['5:0'], 0)
    . $xrefStreamRow(1, $offsets['7:0'], 0)
    . $xrefStreamRow(1, $previousOffsets['8:0'], 0)
    . $xrefStreamRow(1, $previousOffsets['9:0'], 0)
    . $xrefStreamRow(1, $prevHelperOffset, 0);
$compressedRows = gzcompress($rows);
if (!is_string($compressedRows)) {
    throw new RuntimeException('Unable to compress indirect Prev action-review xref rows.');
}

$currentXrefOffset = strlen($pdf);
$pdf .= "20 0 obj\n"
    . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev 30 0 R /Index [1 5 7 3 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
    . "stream\n{$compressedRows}\nendstream\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 274.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 274.0, 718.0],
            'spans' => [[
                'text' => 'Current indirect Prev action docs',
                'bbox' => [72.0, 700.0, 274.0, 718.0],
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
    'support_component' => 'native-pdf-xref-prev-chain-action-review-indirect-prev',
    'native_boundary' => 'indirect xref Prev helper is resolved before repairing same-generation action review rows for WordPress link promotion',
    'previous_xref_offset' => $previousXrefOffset,
    'prev_helper_offset' => $prevHelperOffset,
    'current_xref_offset' => $currentXrefOffset,
    'annotation_objects' => array_column($annotations[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'indirect_prev_helper_used' => str_contains($pdf, '/Prev 30 0 R'),
    'current_uri_promoted' => str_contains($wordpressText, 'https://example.com/current-indirect-prev-action'),
    'current_additional_action_reviewed' => str_contains($encodedReview, 'mailto:current-indirect-prev-action@example.test'),
    'stale_prev_action_excluded' => !str_contains($encodedReview, 'stale-indirect-prev-action') && !str_contains($encodedReview, 'staleIndirectPrevHover'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $wordpressText !== '[Current indirect Prev action docs](https://example.com/current-indirect-prev-action)'
    || $summary['indirect_prev_helper_used'] !== true
    || $summary['current_additional_action_reviewed'] !== true
    || $summary['stale_prev_action_excluded'] !== true
) {
    throw new RuntimeException('Expected indirect xref Prev action review to select current link actions.');
}

echo '<!-- markerpdf-pdf-xref-prev-chain-action-review-indirect-prev-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
