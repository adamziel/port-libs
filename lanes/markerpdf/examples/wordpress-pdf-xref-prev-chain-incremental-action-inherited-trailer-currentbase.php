<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale inherited action link) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited action docs) Tj ET';

$pdf = "%PDF-1.7\n";
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
    $offset = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};
$xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

$staleCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$stalePagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$stalePageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$staleContentOffset = $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$staleAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 304 718] /F 4 /Contents (Inherited trailer action link) /A 8 0 R /AA << /E 9 0 R >> >>');
$staleActionOffset = $addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-inherited-action) >>');
$staleAdditionalActionOffset = $addObject(9, 0, '<< /S /JavaScript /JS (staleInheritedTrailerAction()) >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefTableRow(0, 65535, 'f')
    . $xrefTableRow($staleCatalogOffset)
    . $xrefTableRow($stalePagesOffset)
    . $xrefTableRow($stalePageOffset)
    . $xrefTableRow($staleContentOffset)
    . $xrefTableRow($fontOffset)
    . $xrefTableRow(0, 0, 'f')
    . $xrefTableRow($staleAnnotationOffset)
    . $xrefTableRow($staleActionOffset)
    . $xrefTableRow($staleAdditionalActionOffset)
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 304 718] /F 4 /Contents (Inherited trailer action link) /A 8 0 R /AA << /E 9 0 R >> >>');
$currentActionOffset = $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-inherited-action) >>');
$currentAdditionalActionOffset = $addObject(9, 0, '<< /S /URI /URI (mailto:current-inherited-action@example.test) >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "5 1\n"
    . $xrefTableRow($fontOffset)
    . "trailer\n<< /Size 21 /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 304.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 304.0, 718.0],
            'spans' => [[
                'text' => 'Current inherited action docs',
                'bbox' => [72.0, 700.0, 304.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$textLines = (new PdfTextExtractor())->extractTextLines($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));

$wordpressText = (string) ($blocks[0]['text'] ?? '');
$latestTrailer = substr($pdf, $currentXrefOffset);
$encodedReview = json_encode([$annotations, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$currentOffsets = [
    $currentCatalogOffset,
    $currentPagesOffset,
    $currentPageOffset,
    $currentContentOffset,
    $currentAnnotationOffset,
    $currentActionOffset,
    $currentAdditionalActionOffset,
];
$allCurrentOffsetsAfterPreviousXref = true;
foreach ($currentOffsets as $offset) {
    $allCurrentOffsetsAfterPreviousXref = $allCurrentOffsetsAfterPreviousXref && $offset > $previousXrefOffset;
}

$summary = [
    'scenario' => 'wordpress-pdf-xref-prev-chain-incremental-action-inherited-trailer-currentbase',
    'support_component' => 'native-pdf-action-review-xref-prev-inherited-trailer-repair',
    'native_boundary' => 'latest sparse classic xref update omits Root and inherits it through Prev before WordPress link promotion',
    'previous_xref_before_current' => $previousXrefOffset < $currentXrefOffset,
    'latest_trailer_has_prev' => str_contains($latestTrailer, '/Prev '),
    'latest_trailer_omits_root' => !str_contains($latestTrailer, '/Root '),
    'latest_trailer_only_lists_font_row' => substr_count($latestTrailer, "\n5 1\n") === 1,
    'current_update_offsets_after_previous_xref' => $allCurrentOffsetsAfterPreviousXref,
    'current_text_selected' => $textLines === ['Current inherited action docs'],
    'current_uri_promoted' => str_contains($wordpressText, 'https://example.com/current-inherited-action'),
    'current_additional_action_reviewed' => str_contains($encodedReview, 'mailto:current-inherited-action@example.test'),
    'stale_prev_action_excluded' => !str_contains($encodedReview, 'stale-inherited-action')
        && !str_contains($encodedReview, 'staleInheritedTrailerAction'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $wordpressText !== '[Current inherited action docs](https://example.com/current-inherited-action)'
    || $summary['current_text_selected'] !== true
    || $summary['current_uri_promoted'] !== true
    || $summary['current_additional_action_reviewed'] !== true
    || $summary['stale_prev_action_excluded'] !== true
) {
    throw new RuntimeException('Expected inherited Prev trailer action review to select current update rows before WordPress link promotion.');
}

echo '<!-- markerpdf-xref-prev-chain-incremental-action-inherited-trailer-currentbase-smoke '
    . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
