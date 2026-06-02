<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$objectStreamNestedTokenBoundaryPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Nested object stream page) Tj T* (Boundary parser survived) Tj ET';
    $unreferencedNoise = 'BT /F1 12 Tf 72 720 Td (Unreferenced nested fallback noise) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R /Lang (en-US endobj token in catalog string) /Names << /Dests << /Nested [(99 0 obj) (endobj) (stream)] >> >> >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /PieceInfo << /WP << /Private << /Note (nested << >> [ ] endobj boundary) >> >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        9 => '<< /Type /Catalog /Pages 99 0 R /Note (unlisted compressed catalog) >>',
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
    $addObject(6, 0, "<< /Type /ObjStm /N " . count($members) . " /First {$first} /Length " . strlen($objectStreamPlain) . " >>\nstream\n{$objectStreamPlain}\nendstream");
    $addObject(8, 0, "<< /Length " . strlen($unreferencedNoise) . " >>\nstream\n{$unreferencedNoise}\nendstream");

    $xrefOffset = strlen($pdf);
    $pdf .= "7 0 obj\n"
        . "<< /Type /XRef /Size 10 /Root 1 0 R /Index [1 4] /W [1 1 1] /Filter /FlateDecode /Length " . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps unfiltered object stream members inside nested token boundaries before WordPress text extraction' => static function (TestRunner $t) use ($objectStreamNestedTokenBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $objectStreamNestedTokenBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same(['Nested object stream page', 'Boundary parser survived'], $extractor->extractTextLines($pdf));
        $t->same(['Nested object stream page', 'Boundary parser survived'], $extractor->extractTextRuns($pdf));
        $t->same("Nested object stream page\nBoundary parser survived", $text);
        $t->same("Nested object stream page\nBoundary parser survived\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unreferenced nested fallback noise'));
        $t->true(!str_contains($text, 'unlisted compressed catalog'));
        $t->true(!str_contains($text, "\0"));
    },
];
