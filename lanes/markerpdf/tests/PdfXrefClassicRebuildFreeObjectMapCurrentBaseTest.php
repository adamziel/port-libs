<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicRebuildFreeObjectMapCurrentBasePdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous damaged-startxref free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged-startxref free-map page) Tj ET';

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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale damaged-startxref free annotation) /A << /S /URI /URI (https://stale.example.com/damaged-startxref-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset];
};

$xrefClassicRebuildFreeObjectMapLiteralDecoyPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous literal-decoy free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current literal-decoy free-map page) Tj ET';

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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 718] /Contents (Stale literal-decoy free annotation) /A << /S /URI /URI (https://stale.example.com/literal-decoy-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

    $literalDecoyOffset = strlen($pdf);
    $pdf .= "(\n"
        . "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . ")\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $literalDecoyOffset];
};

return [
    'rebuilds damaged classic startxref for the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapCurrentBasePdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset] = $xrefClassicRebuildFreeObjectMapCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true(isset($freeObjects[7]), 'Damaged classic startxref rebuild must preserve current free rows.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $linkExtractor->extractPageLinks($pdf), 'The stale freed annotation must not be promoted to a WordPress link.');
        $t->same([], $annotationExtractor->extractPageAnnotations($pdf), 'The stale freed annotation must not become review metadata.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 250.0, 718.0],
                    'spans' => [[
                        'text' => 'Current damaged-startxref free-map page',
                        'bbox' => [72.0, 700.0, 250.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(!str_contains($encodedReview, 'stale.example.com'));
        $t->true(!str_contains($encodedReview, 'Stale damaged-startxref free annotation'));
        $t->true(str_contains($pdf, "startxref\n999999"));
    },
    'ignores literal-string xref decoy while rebuilding the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapLiteralDecoyPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $literalDecoyOffset] = $xrefClassicRebuildFreeObjectMapLiteralDecoyPdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($literalDecoyOffset > $currentXrefOffset);
        $t->true(isset($freeObjects[7]), 'Literal-string xref decoys must not replace the current free-row map.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $linkExtractor->extractPageLinks($pdf), 'The stale literal-decoy annotation URI must remain suppressed.');
        $t->same([], $annotationExtractor->extractPageAnnotations($pdf), 'The stale literal-decoy annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 718.0],
                    'spans' => [[
                        'text' => 'Current literal-decoy free-map page',
                        'bbox' => [72.0, 700.0, 260.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "(\nxref\n"));
        $t->true(str_contains($pdf, "startxref\n999999"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/literal-decoy-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale literal-decoy free annotation'));
    },
];
