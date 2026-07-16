<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$xrefPrevChainFreedAnnotationCurrentBasePdf = static function (): string {
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

    return $pdf;
};

return [
    'keeps current xref free annotation rows closed before WordPress link promotion' => static function (TestRunner $t) use (
        $xrefPrevChainFreedAnnotationCurrentBasePdf
    ): void {
        $pdf = $xrefPrevChainFreedAnnotationCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();

        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->same([], $links, 'A stale annotation object freed by the current xref table must not become a link.');
        $t->same([], $annotations, 'A stale annotation object freed by the current xref table must not become annotation metadata.');
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(str_contains($pdf, '0000000000 00001 f'));

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
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));

        $encodedReview = json_encode([$links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encodedReview, 'stale.example.com'));
        $t->true(!str_contains($encodedReview, 'Stale freed annotation'));
    },
];
