<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefOffsetOwnerBoundaryCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current owner boundary page) Tj T* (Xref offset owner kept) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale owner xref page) Tj T* (Stream-owned xref leak) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $addObject(9, 0, '<< /Type /Catalog /Pages 10 0 R >>');
    $addObject(10, 0, '<< /Type /Pages /Kids [11 0 R] /Count 1 >>');
    $addObject(11, 0, '<< /Type /Page /Parent 10 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 12 0 R >>');
    $addObject(12, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $fakeXref = "xref\n"
        . "0 13\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets['3:0'])
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets['9:0'])
        . $xrefRow($offsets['10:0'])
        . $xrefRow($offsets['11:0'])
        . $xrefRow($offsets['12:0'])
        . "trailer\n<< /Size 13 /Root 9 0 R >>\n";
    $fakeOwnerPrefix = "8 0 obj\n<< /Length " . strlen($fakeXref) . " >>\nstream\n";
    $fakeXrefOffset = strlen($pdf) + strlen($fakeOwnerPrefix);
    $offsets['8:0'] = strlen($pdf);
    $pdf .= $fakeOwnerPrefix . $fakeXref . "endstream\nendobj\n";

    $pdf .= "xref\n"
        . "0 13\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow($offsets['8:0'])
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 13 /Root 1 0 R >>\n"
        . "startxref\n{$fakeXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects stream-owned startxref table offsets before current-base WordPress text extraction' => static function (TestRunner $t) use ($parserXrefOffsetOwnerBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefOffsetOwnerBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current owner boundary page', 'Xref offset owner kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current owner boundary page', 'Xref offset owner kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current owner boundary page\nXref offset owner kept", $text);
        $t->same("Current owner boundary page\nXref offset owner kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale owner xref page'));
        $t->true(!str_contains($text, 'Stream-owned xref leak'));
        $t->true(!str_contains($text, 'xref'));
        $t->true(!str_contains($text, "\0"));
    },
];
