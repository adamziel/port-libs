<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$previousContent = 'BT /F1 12 Tf 72 720 Td (Previous docs highlight smoke) Tj ET';
$currentContent = 'BT /F1 12 Tf 72 720 Td (Current docs highlight smoke) Tj ET';

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
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 9 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
$addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
$addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Current link smoke review) /A << /S /URI /URI (https://example.com/current-docs-smoke) >> >>');
$addObject(9, 0, '<< /Type /Annot /Subtype /Highlight /Rect [180 700 340 718] /QuadPoints [180 718 340 718 180 700 340 700] /Contents (Stale freed highlight smoke review) /T (Stale smoke reviewer) /C [1 0 0] >>');

$previousXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "0 10\n"
    . $xrefRow(0, 65535, 'f')
    . $xrefRow($offsets['1:0'])
    . $xrefRow($offsets['2:0'])
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . $xrefRow($offsets['5:0'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['7:0'])
    . $xrefRow(0, 0, 'f')
    . $xrefRow($offsets['9:0'])
    . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
    . "startxref\n{$previousXrefOffset}\n%%EOF\n";

$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R 9 0 R] /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

$currentXrefOffset = strlen($pdf);
$pdf .= "xref\n"
    . "3 2\n"
    . $xrefRow($offsets['3:0'])
    . $xrefRow($offsets['4:0'])
    . "9 1\n"
    . $xrefRow(0, 1, 'f')
    . "trailer\n<< /Size 10 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
    . "startxref\n{$currentXrefOffset}\n%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 340.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 340.0, 718.0],
            'spans' => [
                ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' highlight smoke', 'bbox' => [180.0, 700.0, 340.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
$linkExtractor = new PdfLinkAnnotationExtractor();
$markupExtractor = new PdfMarkupAnnotationExtractor();
$links = $linkExtractor->extractPageLinks($pdf);
$markups = $markupExtractor->extractPageMarkups($pdf);
$linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
$reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
$blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
$encodedReview = json_encode([$annotationPages, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';
$wordpressText = (string) ($blocks[0]['text'] ?? '');

if (!isset($freeObjects[9])) {
    throw new RuntimeException('Expected current xref table to mark stale Highlight annotation object 9 free.');
}
if (($annotationPages[0]['annotations'][0]['annotation_object'] ?? null) !== 7 || ($links[0]['links'][0]['annotation_object'] ?? null) !== 7) {
    throw new RuntimeException('Expected current Link annotation object 7 to remain reviewable and promoted.');
}
if ($markups !== [] || str_contains($encodedReview, 'Stale freed highlight smoke review')) {
    throw new RuntimeException('Freed Highlight annotation must not become review metadata or span review data.');
}
if ($wordpressText !== '[Current docs](https://example.com/current-docs-smoke) highlight smoke') {
    throw new RuntimeException('Expected only the current Link annotation to affect WordPress Markdown.');
}
if (str_contains($visibleText, 'Previous docs highlight smoke') || str_contains($visibleText, 'Stale freed highlight smoke review')) {
    throw new RuntimeException('Stale annotation review text leaked into visible PDF text.');
}

$summary = [
    'support_component' => 'native-pdf-markup-annotation-free-object-boundary',
    'native_boundary' => 'current xref free rows suppress stale text-markup annotations while preserving current Link annotation span promotion',
    'free_annotation_object_marked' => isset($freeObjects[9]),
    'annotation_objects' => array_column($annotationPages[0]['annotations'] ?? [], 'annotation_object'),
    'promoted_link_objects' => array_column($links[0]['links'] ?? [], 'annotation_object'),
    'markup_count' => count($markups[0]['markups'] ?? []),
    'freed_markup_promoted' => str_contains($encodedReview, 'Stale freed highlight smoke review'),
    'current_link_promoted' => str_contains($wordpressText, 'https://example.com/current-docs-smoke'),
    'visible_text_imported' => str_contains($visibleText, 'Current docs highlight smoke'),
    'annotation_payload_text_visible' => str_contains($visibleText, 'Stale freed highlight smoke review'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf-pdf-markup-annotation-freed-object-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
