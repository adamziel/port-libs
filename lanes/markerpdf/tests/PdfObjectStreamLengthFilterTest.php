<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$objectStreamLengthFilterPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Object stream length filter page) Tj T* (Recovered compressed resources) Tj ET';
    $unreferencedNoise = 'BT /F1 12 Tf 72 720 Td (Unreferenced direct fallback noise) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        9 => '<< /Type /Catalog /Pages 99 0 R >>',
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
    $first = strlen($header) + 1;
    $objectStreamPlain = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($objectStreamPlain);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress focused object-stream fixture.');
    }

    $xrefRows = '';
    foreach ([1, 2, 3, 4] as $objectNumber) {
        $xrefRows .= chr(2) . chr(6) . chr($memberIndexes[$objectNumber]);
    }
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused xref-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf): int {
        $offset = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /ObjStm /N 90 0 R /First 91 0 R /Filter 92 0 R /Length 93 0 R >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(8, 0, "<< /Length " . strlen($unreferencedNoise) . " >>\nstream\n{$unreferencedNoise}\nendstream");
    $addObject(90, 0, '94 0 R');
    $addObject(91, 0, '95 0 R');
    $addObject(92, 0, '/FlateDecode');
    $addObject(93, 0, (string) strlen($compressedObjectStream));
    $addObject(94, 0, (string) count($members));
    $addObject(95, 0, (string) $first);

    $xrefOffset = strlen($pdf);
    $pdf .= "7 0 obj\n"
        . "<< /Type /XRef /Size 96 /Root 1 0 R /Index [1 4] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves indirect object stream length filter count and first offsets before WordPress text extraction' => static function (TestRunner $t) use ($objectStreamLengthFilterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $objectStreamLengthFilterPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Object stream length filter page', 'Recovered compressed resources'], $extractor->extractTextLines($pdf));
        $t->same(['Object stream length filter page', 'Recovered compressed resources'], $extractor->extractTextRuns($pdf));
        $t->same("Object stream length filter page\nRecovered compressed resources", $text);
        $t->same("Object stream length filter page\nRecovered compressed resources\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unreferenced direct fallback noise'));
        $t->true(!str_contains($text, "\0"));
    },
];
