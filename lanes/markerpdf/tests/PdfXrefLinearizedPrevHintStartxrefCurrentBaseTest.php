<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefLinearizedPrevHintStartxrefCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Linearized Prev hint stale page) Tj T* (Hint xref leak) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current startxref linearized page) Tj T* (Prev hint range skipped) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Linearized 1 /L LLLLLLLLLL /H [ HHHHHHHHHA HHHHHHHHHB ] /O 4 /E EEEEEEEEEE /N 1 /T TTTTTTTTTT >>');
    $addObject(2, 0, '<< /Type /Catalog /Pages 3 0 R >>');
    $addObject(3, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(4, 0, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R >>');
    $staleContentOffset = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $fontOffset = $addObject(6, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $fakePrevOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 7\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($staleContentOffset)
        . $xrefTableRow($fontOffset)
        . "trailer\n<< /Size 7 /Root 2 0 R >>\n"
        . "startxref\n{$fakePrevOffset}\n%%EOF\n";
    $fakePrevEnd = strlen($pdf);

    $currentCatalogOffset = $addObject(2, 0, '<< /Type /Catalog /Pages 3 0 R >>');
    $currentPagesOffset = $addObject(3, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $currentPageOffset = $addObject(4, 0, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $xrefStreamRow(1, $currentCatalogOffset)
        . $xrefStreamRow(1, $currentPagesOffset)
        . $xrefStreamRow(1, $currentPageOffset)
        . $xrefStreamRow(1, $fontOffset);
    $currentXrefStream = gzcompress($currentRows);
    if (!is_string($currentXrefStream)) {
        throw new RuntimeException('Unable to compress linearized Prev hint current xref fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 2 0 R /Prev ' . $fakePrevOffset . ' /Index [2 3 6 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefStream) . " >>\n"
        . "stream\n{$currentXrefStream}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return strtr($pdf, [
        'LLLLLLLLLL' => sprintf('%010d', strlen($pdf)),
        'HHHHHHHHHA' => sprintf('%010d', $fakePrevOffset),
        'HHHHHHHHHB' => sprintf('%010d', $fakePrevEnd - $fakePrevOffset),
        'EEEEEEEEEE' => sprintf('%010d', $currentCatalogOffset),
        'TTTTTTTTTT' => sprintf('%010d', $currentXrefOffset),
    ]);
};

return [
    'keeps latest startxref current objects when Prev points into a linearized hint range' => static function (
        TestRunner $t
    ) use ($xrefLinearizedPrevHintStartxrefCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefLinearizedPrevHintStartxrefCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Current startxref linearized page', 'Prev hint range skipped'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current startxref linearized page\nPrev hint range skipped", $text);
        $t->same("Current startxref linearized page\nPrev hint range skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Linearized Prev hint stale page'));
        $t->true(!str_contains($text, 'Hint xref leak'));
        $t->true(!str_contains($text, "\0"));
    },
];
