<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterStackBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$parserStreamFilterStackBoundaryCurrentBasePdf = static function () use ($parserStreamFilterStackBoundaryCurrentBaseAscii85): string {
    $before = "BT /F1 12 Tf 72 720 Td (Before ASCII85 Stack Boundary) Tj ET\n";
    while (strlen($before) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (After ASCII85 Stack Boundary) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85($before)
        . "\nendstream\n!"
        . $parserStreamFilterStackBoundaryCurrentBaseAscii85($after)
        . '~>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ null /ASCII85Decode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Before ASCII85 Stack Boundary', 'After ASCII85 Stack Boundary'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Before ASCII85 Stack Boundary\nAfter ASCII85 Stack Boundary", $text);
        $t->same("Before ASCII85 Stack Boundary\nAfter ASCII85 Stack Boundary\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, "\0"));
    },
];
