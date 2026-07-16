<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale classic action page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current classic action docs) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
    $offset = strlen($pdf);
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

    return $offset;
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Classic table action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-classic-prev-action) >>');
$addObject(9, 0, '<< /S /JavaScript /JS (staleClassicPrevHover()) >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0:0'])
    . $xrefRow($offsets['2:0:1'])
    . $xrefRow($offsets['3:0:2'])
    . $xrefRow($offsets['4:0:3'])
    . $xrefRow($offsets['5:0:4'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['7:0:5'])
    . $xrefRow($offsets['8:0:6'])
    . $xrefRow($offsets['9:0:7'])
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$currentCatalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$currentPagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$currentAnnotOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Classic table action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
$currentActionOffset = $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-classic-prev-action) >>');
$currentAdditionalActionOffset = $addObject(9, 0, '<< /S /URI /URI (mailto:current-classic-action@example.test) >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "1 5\n"
    . $xrefRow($currentCatalogOffset)
    . $xrefRow($currentPagesOffset)
    . $xrefRow($currentPageOffset)
    . $xrefRow($currentContentOffset)
    . $xrefRow($offsets['5:0:4'])
    . "7 3\n"
    . $xrefRow($currentAnnotOffset)
    . $xrefRow($currentActionOffset)
    . $xrefRow($currentAdditionalActionOffset)
    . "trailer\n<< /Size 21 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "8 0 obj\n<< /S /URI /URI (https://example.com/post-xref-stale-action-decoy) >>\nendobj\n"
    . "9 0 obj\n<< /S /JavaScript /JS (postXrefStaleHover()) >>\nendobj\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$linkedPages = $linkExtractor->applyLinksToPages([[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 218.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 218.0, 718.0],
            'spans' => [[
                'text' => 'Current classic action docs',
                'bbox' => [72.0, 700.0, 218.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]], $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$annotationPages, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$usesCurrentAction = ($annotationPages[0]['annotations'][0]['actions'][0]['uri'] ?? null) === 'https://example.com/current-classic-prev-action'
    && ($annotationPages[0]['annotations'][0]['additional_actions'][0]['uri'] ?? null) === 'mailto:current-classic-action@example.test'
    && ($blocks[0]['text'] ?? null) === '[Current classic action docs](https://example.com/current-classic-prev-action)';
$staleExcluded = !str_contains($encodedReview, 'post-xref-stale-action-decoy')
    && !str_contains($encodedReview, 'postXrefStaleHover')
    && !str_contains($encodedReview, 'stale-classic-prev-action')
    && !str_contains($encodedReview, 'staleClassicPrevHover');

if (!$usesCurrentAction || !$staleExcluded) {
    throw new RuntimeException('Expected latest classic xref-table action rows to exclude post-xref action decoys.');
}

echo '<!-- markerpdf-xref-prev-chain-classic-action-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-action-review-xref-prev-chain',
    'support_component' => 'native-pdf-classic-xref-action-selection',
    'native_boundary' => 'classic xref-table /Prev action rows selected before same-number post-xref decoys',
    'current_uri' => $annotationPages[0]['annotations'][0]['actions'][0]['uri'] ?? null,
    'current_additional_uri' => $annotationPages[0]['annotations'][0]['additional_actions'][0]['uri'] ?? null,
    'wordpress_markdown' => $blocks[0]['text'] ?? null,
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'post_xref_decoy_excluded' => $staleExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
