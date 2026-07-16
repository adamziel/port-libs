<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);
$staleContent = 'BT /F1 12 Tf 72 720 Td (Stale object-owned review page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current object-owned review page) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber][] = strlen($pdf);
    $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
};

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 718] /F 4 /Contents (Stale object-owned review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, '<< /S /URI /URI (https://stale.example.com/object-owned-review) >>');
$addObject(9, '<< /S /JavaScript /JS (staleObjectOwnedReview\(\)) >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1][0])
    . $xrefRow($offsets[2][0])
    . $xrefRow($offsets[3][0])
    . $xrefRow($offsets[4][0])
    . $xrefRow($offsets[5][0])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[7][0])
    . $xrefRow($offsets[8][0])
    . $xrefRow($offsets[9][0])
    . "trailer\n<< /Size 64 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 718] /F 4 /Contents (Current object-owned review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, '<< /S /URI /URI (https://example.com/current-object-owned-review) >>');
$addObject(9, '<< /S /URI /URI (mailto:current-object-owned-review@example.test) >>');

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets[1][1])
    . $xrefRow($offsets[2][1])
    . $xrefRow($offsets[3][1])
    . $xrefRow($offsets[4][1])
    . $xrefRow($offsets[5][1])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets[7][1])
    . $xrefRow($offsets[8][1])
    . $xrefRow($offsets[9][1])
    . "trailer\n<< /Size 64 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

$objectOwnedStartxrefOffset = strlen($pdf);
$addObject(60, "<< /ProducerNote (damaged writer left startxref\n0\ninside a private object after the current xref table) >>");

$textExtractor = new PdfTextExtractor();
$plainText = $textExtractor->extractPlainText($pdf);
$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages([[
    'page' => 1,
    'blocks' => [[
        'type' => 'text',
        'lines' => [[
            'spans' => [[
                'text' => 'Current object-owned review page',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]], $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$encodedReview = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

$summary = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-review-object-owned-currentbase',
    'native_boundary' => 'object-owned startxref tokens bound classic xref rebuild scans without selecting stale annotation actions',
    'previous_xref_offset' => $previousXrefOffset,
    'current_xref_offset' => $currentXrefOffset,
    'object_owned_startxref_token_offset' => $objectOwnedStartxrefOffset,
    'uses_current_text' => $plainText === 'Current object-owned review page',
    'annotation_uri_current' => ($annotationPages[0]['annotations'][0]['actions'][0]['uri'] ?? null) === 'https://example.com/current-object-owned-review',
    'additional_action_current' => ($annotationPages[0]['annotations'][0]['additional_actions'][0]['uri'] ?? null) === 'mailto:current-object-owned-review@example.test',
    'markdown_link_current' => ($blocks[0]['text'] ?? null) === '[Current object-owned review page](https://example.com/current-object-owned-review)',
    'excludes_stale_uri' => !str_contains($encodedReview, 'https://stale.example.com/object-owned-review'),
    'excludes_stale_javascript' => !str_contains($encodedReview, 'staleObjectOwnedReview'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$summary['uses_current_text']
    || !$summary['annotation_uri_current']
    || !$summary['additional_action_current']
    || !$summary['markdown_link_current']
    || !$summary['excludes_stale_uri']
    || !$summary['excludes_stale_javascript']
) {
    throw new RuntimeException('Expected object-owned startxref rebuild boundary to keep annotation review on current rows.');
}

echo '<!-- markerpdf-xref-classic-rebuild-review-object-owned-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
