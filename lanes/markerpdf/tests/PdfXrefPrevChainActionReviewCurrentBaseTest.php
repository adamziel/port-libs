<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$xrefPrevChainActionReviewPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale action review link) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current action docs) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-prev-chain-action) >>');
    $addObject(9, 0, '<< /S /JavaScript /JS (stalePrevChainHover\\(\\)) >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";
    $previousOffsets = $offsets;

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 218 718] /F 4 /Contents (Action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-prev-chain-action) >>');
    $addObject(9, 0, '<< /S /URI /URI (mailto:current-prev-chain@example.test) >>');

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:0'], 0)
        . $xrefStreamRow(1, $offsets['2:0'], 0)
        . $xrefStreamRow(1, $offsets['3:0'], 0)
        . $xrefStreamRow(1, $offsets['4:0'], 0)
        . $xrefStreamRow(1, $offsets['5:0'], 0)
        . $xrefStreamRow(1, $offsets['7:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['8:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['9:0'], 0);
    $compressedCurrentRows = gzcompress($currentRows);
    if (!is_string($compressedCurrentRows)) {
        throw new RuntimeException('Unable to compress current action-review xref-stream rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 5 7 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedCurrentRows) . " >>\n"
        . "stream\n{$compressedCurrentRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainActionReviewPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 218.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 218.0, 718.0],
                'spans' => [[
                    'text' => 'Current action docs',
                    'bbox' => [72.0, 700.0, 218.0, 718.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'repairs action review xref-stream rows through Prev chain before WordPress link promotion' => static function (
        TestRunner $t
    ) use ($xrefPrevChainActionReviewPdf, $xrefPrevChainActionReviewPages): void {
        $pdf = $xrefPrevChainActionReviewPdf();

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages));
        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object']);
        $t->same('https://example.com/current-prev-chain-action', $annotation['actions'][0]['uri']);
        $t->same('mailto:current-prev-chain@example.test', $annotation['additional_actions'][0]['uri']);

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links));
        $t->same(1, count($links[0]['links']));
        $t->same(7, $links[0]['links'][0]['annotation_object']);
        $t->same('https://example.com/current-prev-chain-action', $links[0]['links'][0]['uri']);
        $t->same('mailto:current-prev-chain@example.test', $links[0]['links'][0]['additional_actions'][0]['uri']);

        $linkedPages = $linkExtractor->applyLinksToPages($xrefPrevChainActionReviewPages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-prev-chain-action', $span['link_uri']);
        $t->same('mailto:current-prev-chain@example.test', $span['link_additional_actions_review'][0]['uri']);

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current action docs](https://example.com/current-prev-chain-action)', $blocks[0]['text']);

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(str_contains($pdf, '/Prev '));
        $t->true(!str_contains($encoded, 'stale-prev-chain-action'));
        $t->true(!str_contains($encoded, 'stalePrevChainHover'));
    },
];
