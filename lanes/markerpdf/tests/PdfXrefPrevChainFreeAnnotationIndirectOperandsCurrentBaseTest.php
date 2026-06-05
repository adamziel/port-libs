<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefPrevChainFreeAnnotationIndirectOperandsCurrentBasePdf = static function (): string {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous indirect free annotation page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect free annotation page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale indirect free annotation) /A << /S /URI /URI (https://stale.example.com/indirect-free-annotation) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['7:0'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(30, 0, '[1 4 1]');
    $addObject(31, 0, '[3 2 7 1]');

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['3:0'], 0)
        . $xrefStreamRow(1, $offsets['4:0'], 0)
        . $xrefStreamRow(0, 0, 1);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress indirect free-entry xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /W 30 0 R /Index 31 0 R /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n";

    $addObject(30, 0, '[1 1 1]');
    $addObject(31, 0, '[7 1]');
    $pdf .= "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'suppresses stale page annotations when current xref-stream free rows use indirect W and Index operands' => static function (TestRunner $t) use (
        $xrefPrevChainFreeAnnotationIndirectOperandsCurrentBasePdf
    ): void {
        $pdf = $xrefPrevChainFreeAnnotationIndirectOperandsCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true(isset($freeObjects[7]), 'The lightweight free-object map must resolve indirect xref-stream W and Index operands.');
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
                        'text' => 'Current indirect free annotation page',
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
        $t->true(str_contains($pdf, '/W 30 0 R'));
        $t->true(str_contains($pdf, '/Index 31 0 R'));
        $encoded = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'stale.example.com'));
        $t->true(!str_contains($encoded, 'Stale indirect free annotation'));
    },
];
