<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefPrevChainFreeMapCurrentRowRepairPdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous free-map repair page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current free-map repair page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $catalogOffset = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $pagesOffset = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $previousPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $previousContentOffset = $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $fontOffset = $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 724] /Contents (Previous freed link) /A << /S /URI /URI (https://stale.example.com/free-map-repair) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($catalogOffset)
        . $xrefRow($pagesOffset)
        . $xrefRow($previousPageOffset)
        . $xrefRow($previousContentOffset)
        . $xrefRow($fontOffset)
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $currentAnnotationOffset = $addObject(7, 0, '<< /Type /Annot /Subtype /Link /P 3 0 R /Rect [72 700 260 724] /Contents (Current repaired link) /A << /S /URI /URI (https://current.example.com/free-map-repair) >> >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "30 1\n"
        . $xrefRow($currentAnnotationOffset)
        . "trailer\n<< /Size 31 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs latest Prev-chain free-object map rows by current offset owner before link review' => static function (
        TestRunner $t
    ) use ($xrefPrevChainFreeMapCurrentRowRepairPdf): void {
        $pdf = $xrefPrevChainFreeMapCurrentRowRepairPdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->true(!isset($freeObjects[7]), 'The malformed latest row owns current object 7 before inherited free rows merge.');
        $t->true(isset($freeObjects[6]), 'Unrelated previous free rows remain visible to the free-object map.');
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://current.example.com/free-map-repair', $links[0]['links'][0]['uri']);
        $t->same('Current repaired link', $links[0]['links'][0]['contents']);
        $t->same(1, count($annotations));
        $t->same(1, count($annotations[0]['annotations']));
        $t->same(7, $annotations[0]['annotations'][0]['annotation_object']);
        $t->same('Current repaired link', $annotations[0]['annotations'][0]['contents']);

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 724.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 724.0],
                    'spans' => [[
                        'text' => 'Current free-map repair page',
                        'bbox' => [72.0, 700.0, 260.0, 724.0],
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->same('https://current.example.com/free-map-repair', $span['link_uri']);
        $t->same(7, $span['link_annotation_object']);
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, "\n30 1\n"));
        $t->true(!str_contains($encodedReview, 'stale.example.com'));
        $t->true(!str_contains($encodedReview, 'Previous freed link'));
    },
];
