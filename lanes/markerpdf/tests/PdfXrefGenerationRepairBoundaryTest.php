<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefGenerationRepairBoundaryPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed previous generation page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current direct generation page) Tj T* (Hybrid table boundary kept) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $objectStreamPlain = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress object stream fixture.');
    }

    $xrefRows = chr(2) . chr(6) . chr($memberIndexes[4]);
    $compressedHybridXref = gzcompress($xrefRows);
    if (!is_string($compressedHybridXref)) {
        throw new RuntimeException('Unable to compress hybrid xref-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRows = static function (array $rows): string {
        $encoded = '';
        foreach ($rows as $row) {
            [$offset, $generation] = $row;
            $encoded .= chr(1) . pack('N', $offset) . chr($generation);
        }

        return $encoded;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridXref) . " >>\nstream\n{$compressedHybridXref}\nendstream");

    $previousRows = $xrefStreamRows([
        [$offsets['1:0'], 0],
        [$offsets['2:0'], 0],
        [$offsets['3:0'], 0],
        [$offsets['5:0'], 0],
        [$offsets['6:0'], 0],
        [$offsets['7:0'], 0],
    ]);
    $previousCompressed = gzcompress($previousRows);
    if (!is_string($previousCompressed)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
    $addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n" . $xrefTableRow(0, 65535, 'f')
        . "1 2\n" . $xrefTableRow($offsets['1:1'], 1) . $xrefTableRow($offsets['2:1'], 1)
        . "4 1\n" . $xrefTableRow($offsets['4:1'], 1)
        . "9 1\n" . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 21 /Root 1 1 R /Prev {$previousXrefOffset} /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps current hybrid table direct generation before stale xref-stream object member' => static function (TestRunner $t) use ($xrefGenerationRepairBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefGenerationRepairBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current direct generation page', 'Hybrid table boundary kept'], $extractor->extractTextLines($pdf));
        $t->same(['Current direct generation page', 'Hybrid table boundary kept'], $extractor->extractTextRuns($pdf));
        $t->same("Current direct generation page\nHybrid table boundary kept", $text);
        $t->same("Current direct generation page\nHybrid table boundary kept\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale compressed previous generation page'));
        $t->true(!str_contains($text, "\0"));
    },
];
