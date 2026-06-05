<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous annotation page) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current annotation page) Tj ET';
$pdf = "%PDF-1.7\n";
$offsets = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
    $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
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
$addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale freed annotation) /A << /S /URI /URI (https://stale.example.com/freed-annotation) >> >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 8\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['7:0'])
    . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 2\n"
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . "7 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 250.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'spans' => [[
                'text' => 'Current annotation page',
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'font' => 'Helvetica',
            ]],
        ]],
    ]],
]];

$linkExtractor = new PdfLinkAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
$wordpressText = (string) ($blocks[0]['text'] ?? '');

if ($links !== [] || $annotations !== []) {
    throw new RuntimeException('Current xref free annotation row must close stale link and annotation metadata.');
}
if (str_contains($encodedReview, 'stale.example.com') || str_contains($encodedReview, 'Stale freed annotation')) {
    throw new RuntimeException('Stale freed annotation leaked into WordPress review output.');
}
if ($wordpressText !== 'Current annotation page' || !str_contains($visibleText, 'Current annotation page')) {
    throw new RuntimeException('Expected current page text to remain importable without stale link promotion.');
}
if (str_contains($visibleText, 'Previous annotation page')) {
    throw new RuntimeException('Previous revision page text leaked through the current xref chain.');
}

$summary = [
    'support_component' => 'native-pdf-xref-prev-chain-freed-annotation-currentbase',
    'native_boundary' => 'current incremental xref free rows close stale annotation objects before WordPress link promotion',
    'startxref_uses_prev_chain' => str_contains($pdf, '/Prev '),
    'current_xref_frees_annotation_object' => str_contains($pdf, "7 1\n0000000000 00001 f"),
    'link_pages' => count($links),
    'annotation_pages' => count($annotations),
    'wordpress_markdown' => $wordpressText,
    'stale_annotation_promoted' => str_contains($encodedReview, 'stale.example.com'),
    'visible_text_imported' => str_contains($visibleText, 'Current annotation page'),
    'executes_pdf_actions' => false,
    'executes_javascript' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-xref-prev-chain-freed-annotation-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
