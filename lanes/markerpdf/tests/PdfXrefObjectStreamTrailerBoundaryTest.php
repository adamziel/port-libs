<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamTrailerBoundaryPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current trailer boundary page) Tj T* (Direct page tree repaired) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale unselected object stream page) Tj ET';

    $staleMembers = [
        2 => '<< /Type /Pages /Kids [8 0 R] /Count 1 /Note (unselected page tree overwrite) >>',
        8 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    foreach ($staleMembers as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $compressedObjectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress stale object-stream fixture.');
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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($staleMembers) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 7\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . $xrefRow($offsets['6:0'])
        . "9 1\n"
        . $xrefRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps current trailer page tree before unselected object-stream repair fallback' => static function (TestRunner $t) use ($xrefObjectStreamTrailerBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamTrailerBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Current trailer boundary page', 'Direct page tree repaired'], $extractor->extractTextLines($pdf));
        $t->same(['Current trailer boundary page', 'Direct page tree repaired'], $extractor->extractTextRuns($pdf));
        $t->same("Current trailer boundary page\nDirect page tree repaired", $text);
        $t->same("Current trailer boundary page\nDirect page tree repaired\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale unselected object stream page'));
        $t->true(!str_contains($text, 'unselected page tree overwrite'));
        $t->true(!str_contains($text, "\0"));
    },
];
