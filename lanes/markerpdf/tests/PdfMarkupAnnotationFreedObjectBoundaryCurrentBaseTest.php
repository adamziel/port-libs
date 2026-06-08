<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$markupFreedObjectBoundaryPdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous docs highlight) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current docs highlight) Tj ET';

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
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 170 718] /Contents (Current link review) /A << /S /URI /URI (https://example.com/current-docs) >> >>');
    $addObject(9, 0, '<< /Type /Annot /Subtype /Highlight /Rect [180 700 300 718] /QuadPoints [180 718 300 718 180 700 300 700] /Contents (Stale freed highlight review) /T (Stale reviewer) /C [1 0 0] >>');

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

    return $pdf;
};

$markupFreedObjectBoundaryPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 300.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 300.0, 718.0],
                'spans' => [
                    ['text' => 'Current docs', 'bbox' => [72.0, 700.0, 170.0, 718.0], 'font' => 'Helvetica'],
                    ['text' => ' highlight', 'bbox' => [180.0, 700.0, 300.0, 718.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ]];
};

return [
    'suppresses xref-free text markup annotations while preserving current link promotion' => static function (
        TestRunner $t
    ) use ($markupFreedObjectBoundaryPdf, $markupFreedObjectBoundaryPages): void {
        $pdf = $markupFreedObjectBoundaryPdf();

        $annotations = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotations));
        $t->same([7], array_column($annotations[0]['annotations'], 'annotation_object'));

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same([7], array_column($links[0]['links'], 'annotation_object'));
        $t->same('https://example.com/current-docs', $links[0]['links'][0]['uri']);

        $markupExtractor = new PdfMarkupAnnotationExtractor();
        $markups = $markupExtractor->extractPageMarkups($pdf);
        $t->same([], $markups, 'A text markup annotation freed by the current xref table must not become review metadata.');

        $linkedPages = $linkExtractor->applyLinksToPages($markupFreedObjectBoundaryPages(), $pdf);
        $reviewPages = $markupExtractor->applyMarkupsToPages($linkedPages, $pdf);
        $spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'];
        $t->same('https://example.com/current-docs', $spans[0]['link_uri']);
        $t->true(!isset($spans[1]['review_annotations']));

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($reviewPages));
        $t->same('[Current docs](https://example.com/current-docs) highlight', $blocks[0]['text']);

        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $t->contains('Current docs highlight', $plainText);
        foreach ([
            'Previous docs highlight',
            'Stale freed highlight review',
            'Stale reviewer',
        ] as $reviewOnlyText) {
            $t->same(false, str_contains($plainText, $reviewOnlyText));
        }

        $encodedReview = json_encode([$annotations, $links, $markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->same(false, str_contains($encodedReview, 'Stale freed highlight review'));
        $t->same(false, str_contains($encodedReview, 'Stale reviewer'));
    },
];
