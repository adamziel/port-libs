<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentContent = 'BT /F1 12 Tf 72 720 Td (Current action docs) Tj ET';

$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = strlen($pdf);
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};
$xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
    "%010d %05d %s \n",
    $offset,
    $generation,
    $state
);

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Current action review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://example.com/current-classic-rebuild-action) >>');
$addObject(9, 0, '<< /S /URI /URI (mailto:current-classic-rebuild@example.test) >>');

$currentXrefOffset = strlen($pdf);
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
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n";

$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Stale decoy action review) /A 8 0 R /AA << /E 9 0 R >> >>');
$addObject(8, 0, '<< /S /URI /URI (https://stale.example.com/classic-rebuild-action-decoy) >>');
$addObject(9, 0, '<< /S /JavaScript /JS (staleClassicRebuildAction\(\)) >>');
$pdf .= "startxref\n999999\n%%EOF";

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$linkedPages = $linkExtractor->applyLinksToPages([[
    'page' => 1,
    'blocks' => [[
        'type' => 'text',
        'lines' => [[
            'spans' => [[
                'text' => 'Current action docs',
                'bbox' => [72.0, 700.0, 218.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]], $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$encodedReview = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$summary = [
    'scenario' => 'wordpress-pdf-xref-classic-rebuild-action-review-currentbase',
    'native_boundary' => 'damaged classic startxref rebuild selects current annotation action rows before WordPress link promotion',
    'current_xref_offset' => $currentXrefOffset,
    'uses_current_text' => $plainText === 'Current action docs',
    'annotation_uri_current' => ($annotationPages[0]['annotations'][0]['actions'][0]['uri'] ?? null) === 'https://example.com/current-classic-rebuild-action',
    'additional_action_current' => ($annotationPages[0]['annotations'][0]['additional_actions'][0]['uri'] ?? null) === 'mailto:current-classic-rebuild@example.test',
    'markdown_link_current' => ($blocks[0]['text'] ?? null) === '[Current action docs](https://example.com/current-classic-rebuild-action)',
    'excludes_stale_uri' => !str_contains($encodedReview, 'https://stale.example.com/classic-rebuild-action-decoy'),
    'excludes_stale_javascript' => !str_contains($encodedReview, 'staleClassicRebuildAction'),
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
    throw new RuntimeException('Expected damaged classic startxref rebuild to keep annotation actions on the current rows.');
}

echo '<!-- markerpdf-xref-classic-rebuild-action-review-currentbase-smoke ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($blocks[0]['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
