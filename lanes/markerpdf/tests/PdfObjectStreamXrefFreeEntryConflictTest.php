<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$objectStreamXrefFreeEntryConflictPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale conflicting object stream page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current direct conflict page) Tj T* (Free entry boundary kept) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = $memberIndex;
        $objectData .= $body . "\n";
        $memberIndex++;
    }

    $header = implode(' ', $headerPairs);
    $objectStreamPlain = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress object stream fixture.');
    }

    $xrefRows = chr(2) . chr(6) . chr($memberIndexes[4]);
    $compressedXrefStream = gzcompress($xrefRows);
    if (!is_string($compressedXrefStream)) {
        throw new RuntimeException('Unable to compress xref-stream fixture.');
    }

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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $xrefStreamOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedXrefStream) . " >>\nstream\n{$compressedXrefStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow(0, 2, 'f')
        . $xrefRow($offsets['5:0'])
        . $xrefRow($offsets['6:0'])
        . $xrefRow($offsets['7:0'])
        . $xrefRow($offsets['8:0'])
        . $xrefRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R /XRefStm {$xrefStreamOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'honors current hybrid xref free entry before conflicting object stream member' => static function (TestRunner $t) use ($objectStreamXrefFreeEntryConflictPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $objectStreamXrefFreeEntryConflictPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current direct conflict page', 'Free entry boundary kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current direct conflict page', 'Free entry boundary kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current direct conflict page\nFree entry boundary kept", $text);
        $t->same("Current direct conflict page\nFree entry boundary kept\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Stale conflicting object stream page'));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, "\0"));
    },
];
