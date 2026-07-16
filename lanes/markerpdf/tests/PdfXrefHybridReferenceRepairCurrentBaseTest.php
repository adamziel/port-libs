<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridReferenceRepairCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid reference generation zero page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid reference page) Tj T* (Direct generation reference repaired) Tj ET';

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

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale direct generation zero) >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $hybridRows = $xrefStreamRow(1, $offsets['4:0'], 0);
    $compressedHybridRows = gzcompress($hybridRows);
    if (!is_string($compressedHybridRows)) {
        throw new RuntimeException('Unable to compress hybrid xref-stream reference fixture.');
    }

    $hybridXrefOffset = $addObject(
        7,
        0,
        '<< /Type /XRef /Size 10 /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridRows) . " >>\nstream\n{$compressedHybridRows}\nendstream"
    );

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:1'], 1)
        . $xrefTableRow($offsets['2:1'], 1)
        . $xrefTableRow($offsets['3:0'])
        . "5 5\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($hybridXrefOffset)
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 1 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs hybrid xref direct rows when current page tree references a newer generation' => static function (TestRunner $t) use ($xrefHybridReferenceRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridReferenceRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current hybrid reference page', 'Direct generation reference repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current hybrid reference page', 'Direct generation reference repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current hybrid reference page\nDirect generation reference repaired", $text);
        $t->same("Current hybrid reference page\nDirect generation reference repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale hybrid reference generation zero page'));
        $t->true(!str_contains($text, 'stale direct generation zero'));
        $t->true(!str_contains($text, "\0"));
    },
];
