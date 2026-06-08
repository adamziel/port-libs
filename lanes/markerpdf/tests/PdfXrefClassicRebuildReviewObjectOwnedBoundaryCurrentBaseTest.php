<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefClassicRebuildReviewObjectOwnedBoundaryCurrentBasePdf = static function (): array {
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
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber][] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
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

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $objectOwnedStartxrefOffset];
};

$xrefClassicRebuildReviewObjectOwnedBoundaryPages = static fn (): array => [[
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
]];

return [
    'uses ignored object-owned startxref only as a classic rebuild boundary before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildReviewObjectOwnedBoundaryCurrentBasePdf, $xrefClassicRebuildReviewObjectOwnedBoundaryPages): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $objectOwnedStartxrefOffset] = $xrefClassicRebuildReviewObjectOwnedBoundaryCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $t->same('Current object-owned review page', $textExtractor->extractPlainText($pdf));
        $t->same(['Current object-owned review page'], $textExtractor->extractTextLines($pdf));

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages));
        $t->same(1, count($annotationPages[0]['annotations']));
        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object']);
        $t->same('Current object-owned review', $annotation['contents']);
        $t->same('https://example.com/current-object-owned-review', $annotation['actions'][0]['uri']);
        $t->same('URI', $annotation['additional_actions'][0]['action_type']);
        $t->same('mailto:current-object-owned-review@example.test', $annotation['additional_actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-object-owned-review', $links[0]['links'][0]['uri']);
        $t->same('mailto:current-object-owned-review@example.test', $links[0]['links'][0]['additional_actions'][0]['uri']);

        $linkedPages = $linkExtractor->applyLinksToPages($xrefClassicRebuildReviewObjectOwnedBoundaryPages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-object-owned-review', $span['link_uri']);
        $t->same('mailto:current-object-owned-review@example.test', $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current object-owned review page](https://example.com/current-object-owned-review)', $blocks[0]['text']);

        $encodedReview = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($objectOwnedStartxrefOffset > $currentXrefOffset);
        $t->true(str_contains($pdf, "startxref\n0\ninside a private object"));
        $t->true(!str_contains($encodedReview, 'https://stale.example.com/object-owned-review'));
        $t->true(!str_contains($encodedReview, 'staleObjectOwnedReview'));
    },
];
