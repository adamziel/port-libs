<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridPrevTrailerSizeRepairCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid Prev size page) Tj T* (Trailer size repaired row) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale oversized trailer page) Tj ET';

    $pdf = "%PDF-1.5\n";
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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $previousXrefRows = ''
        . $xrefStreamRow(0, 0, 65535)
        . $xrefStreamRow(1, $offsets['1:0'], 0)
        . $xrefStreamRow(1, $offsets['2:0'], 0)
        . $xrefStreamRow(1, $offsets['3:0'], 0)
        . $xrefStreamRow(1, $offsets['4:0'], 0)
        . $xrefStreamRow(1, $offsets['5:0'], 0);
    $compressedPreviousXref = gzcompress($previousXrefRows);
    if (!is_string($compressedPreviousXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $previousXrefOffset = $addObject(
        6,
        0,
        '<< /Type /XRef /Size 4 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedPreviousXref) . " >>\nstream\n{$compressedPreviousXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(5, 1, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $hybridRows = $xrefStreamRow(1, 0, 0);
    $compressedHybridRows = gzcompress($hybridRows);
    if (!is_string($compressedHybridRows)) {
        throw new RuntimeException('Unable to compress hybrid xref-stream fixture.');
    }

    $hybridXrefOffset = $addObject(
        8,
        0,
        '<< /Type /XRef /Size 9 /Index [8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridRows) . " >>\nstream\n{$compressedHybridRows}\nendstream"
    );

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n" . $xrefTableRow(0, 65535, 'f')
        . "8 1\n" . $xrefTableRow($hybridXrefOffset)
        . "trailer\n<< /Size 6 /Root 1 0 R /Prev {$previousXrefOffset} /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs underdeclared xref stream Size through hybrid Prev chains before WordPress text extraction' => static function (TestRunner $t) use ($xrefHybridPrevTrailerSizeRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridPrevTrailerSizeRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current hybrid Prev size page', 'Trailer size repaired row'], $extractor->extractTextLines($pdf));
        $t->same(['Current hybrid Prev size page', 'Trailer size repaired row'], $extractor->extractTextRuns($pdf));
        $t->same("Current hybrid Prev size page\nTrailer size repaired row", $text);
        $t->same("Current hybrid Prev size page\nTrailer size repaired row\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale oversized trailer page'));
        $t->true(!str_contains($text, "\0"));
    },
];
