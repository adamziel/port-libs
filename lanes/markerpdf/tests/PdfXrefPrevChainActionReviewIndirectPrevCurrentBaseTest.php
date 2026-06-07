<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;

$xrefPrevChainActionReviewIndirectPrevPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev action link) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev action docs) Tj ET';

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
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Indirect Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/stale-indirect-prev-action) >>');
    $addObject(9, 0, '<< /S /JavaScript /JS (staleIndirectPrevHover()) >>');

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
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 274 718] /F 4 /Contents (Indirect Prev action review link) /A 8 0 R /AA << /E 9 0 R >> >>');
    $addObject(8, 0, '<< /S /URI /URI (https://example.com/current-indirect-prev-action) >>');
    $addObject(9, 0, '<< /S /URI /URI (mailto:current-indirect-prev-action@example.test) >>');
    $prevHelperOffset = $addObject(30, 0, (string) $previousXrefOffset);

    $currentRows = ''
        . $xrefStreamRow(1, $offsets['1:0'], 0)
        . $xrefStreamRow(1, $offsets['2:0'], 0)
        . $xrefStreamRow(1, $offsets['3:0'], 0)
        . $xrefStreamRow(1, $offsets['4:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['5:0'], 0)
        . $xrefStreamRow(1, $offsets['7:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['8:0'], 0)
        . $xrefStreamRow(1, $previousOffsets['9:0'], 0)
        . $xrefStreamRow(1, $prevHelperOffset, 0);
    $compressedRows = gzcompress($currentRows);
    if (!is_string($compressedRows)) {
        throw new RuntimeException('Unable to compress indirect Prev action-review xref rows.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Prev 30 0 R /Index [1 5 7 3 30 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedRows) . " >>\n"
        . "stream\n{$compressedRows}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$xrefPrevChainActionReviewIndirectPrevPages = static function (): array {
    return [[
        'pnum' => 0,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [72.0, 700.0, 274.0, 718.0],
            'lines' => [[
                'bbox' => [72.0, 700.0, 274.0, 718.0],
                'spans' => [[
                    'text' => 'Current indirect Prev action docs',
                    'bbox' => [72.0, 700.0, 274.0, 718.0],
                    'font' => 'Helvetica',
                ]],
            ]],
        ]],
    ]];
};

return [
    'resolves indirect xref Prev before repairing action review current rows' => static function (
        TestRunner $t
    ) use ($xrefPrevChainActionReviewIndirectPrevPdf, $xrefPrevChainActionReviewIndirectPrevPages): void {
        $pdf = $xrefPrevChainActionReviewIndirectPrevPdf();

        $t->true(str_contains($pdf, '/Prev 30 0 R'), 'fixture stores current xref /Prev as an indirect helper');
        $t->true(str_contains($pdf, 'https://example.com/stale-indirect-prev-action'), 'fixture contains stale action bytes');
        $t->true(str_contains($pdf, 'https://example.com/current-indirect-prev-action'), 'fixture contains current action bytes');

        $annotationPages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);
        $t->same(1, count($annotationPages), 'one annotation page is extracted');
        $annotation = $annotationPages[0]['annotations'][0];
        $t->same(7, $annotation['annotation_object'], 'current annotation object is selected');
        $t->same('https://example.com/current-indirect-prev-action', $annotation['actions'][0]['uri'], 'current primary action wins through indirect Prev repair');
        $t->same('mailto:current-indirect-prev-action@example.test', $annotation['additional_actions'][0]['uri'], 'current additional action wins through indirect Prev repair');

        $linkExtractor = new PdfLinkAnnotationExtractor();
        $links = $linkExtractor->extractPageLinks($pdf);
        $t->same(1, count($links), 'one link page is extracted');
        $t->same(1, count($links[0]['links']), 'one link is promoted');
        $t->same(7, $links[0]['links'][0]['annotation_object'], 'promoted link keeps the current annotation object');
        $t->same('https://example.com/current-indirect-prev-action', $links[0]['links'][0]['uri'], 'promoted link uses the current URI');
        $t->same('mailto:current-indirect-prev-action@example.test', $links[0]['links'][0]['additional_actions'][0]['uri'], 'promoted link carries current additional action review');

        $linkedPages = $linkExtractor->applyLinksToPages($xrefPrevChainActionReviewIndirectPrevPages(), $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $t->same('https://example.com/current-indirect-prev-action', $span['link_uri'], 'WordPress span promotion uses current URI');
        $t->same('mailto:current-indirect-prev-action@example.test', $span['link_additional_actions_review'][0]['uri'], 'WordPress span review uses current additional action');

        $blocks = (new MarkdownPostProcessor())->mergeBlocks((new MarkdownPostProcessor())->mergeSpans($linkedPages));
        $t->same('[Current indirect Prev action docs](https://example.com/current-indirect-prev-action)', $blocks[0]['text'], 'Markdown block promotes the current URI');

        $encoded = json_encode([$annotationPages, $links, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, 'stale-indirect-prev-action'), 'stale primary action is excluded from review output');
        $t->true(!str_contains($encoded, 'staleIndirectPrevHover'), 'stale JavaScript additional action is excluded from review output');
    },
];
