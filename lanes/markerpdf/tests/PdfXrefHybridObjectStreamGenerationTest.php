<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefHybridObjectStreamGenerationPdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale compressed generation zero page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid generation page) Tj T* (Referenced generation one recovered) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale compressed generation zero) >>',
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
    $compressedObjectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress object-stream fixture.');
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

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [4 1 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 1, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 1 R >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $hybridXrefOffset = $addObject(7, 0, '<< /Type /XRef /Size 10 /Index [4 1] /W [1 1 1] /Filter /FlateDecode /Length ' . strlen($compressedHybridXref) . " >>\nstream\n{$compressedHybridXref}\nendstream");
    $addObject(9, 1, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:1'], 1)
        . $xrefTableRow($offsets['2:1'], 1)
        . $xrefTableRow($offsets['3:0'])
        . "5 5\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow($offsets['7:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['9:1'], 1)
        . "trailer\n<< /Size 10 /Root 1 1 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps referenced generation one direct page before stale hybrid object-stream generation zero' => static function (TestRunner $t) use ($xrefHybridObjectStreamGenerationPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefHybridObjectStreamGenerationPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current hybrid generation page', 'Referenced generation one recovered'], $extractor->extractTextLines($pdf));
        $t->same(['Current hybrid generation page', 'Referenced generation one recovered'], $extractor->extractTextRuns($pdf));
        $t->same("Current hybrid generation page\nReferenced generation one recovered", $text);
        $t->same("Current hybrid generation page\nReferenced generation one recovered\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale compressed generation zero page'));
        $t->true(!str_contains($text, 'stale compressed generation zero'));
        $t->true(!str_contains($text, "\0"));
    },
];
