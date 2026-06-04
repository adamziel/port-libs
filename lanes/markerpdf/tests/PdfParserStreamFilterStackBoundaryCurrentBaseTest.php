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

$parserStreamFilterStackBoundaryCurrentBaseZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused zlib stored-block fixture must fit one deflate block.');
    }

    $s1 = 1;
    $s2 = 0;
    for ($index = 0; $index < $length; $index++) {
        $s1 = ($s1 + ord($bytes[$index])) % 65521;
        $s2 = ($s2 + $s1) % 65521;
    }

    return "\x78\x01"
        . "\x01"
        . pack('v', $length)
        . pack('v', (~$length) & 0xffff)
        . $bytes
        . pack('N', ($s2 << 16) | $s1);
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

$parserStreamFilterStackBoundaryCurrentBaseStackedPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Stacked ASCII85 Flate Before) Tj ET\n";
    while ((7 + strlen($before)) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Stacked ASCII85 Flate After) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($before . $fakeEndstreamBytes . $after)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused ASCII85 stack fixture must contain the fake endstream marker.');
    }
    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseStaleLengthPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused declared-Length fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Declared Length Stack Before) Tj ET\n";
    while ((7 + strlen($before)) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Declared Length Stack After) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($before . $fakeEndstreamBytes . $after)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused declared-Length stack fixture must contain the fake endstream marker.');
    }
    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';
    $declaredLength = strpos($encoded, "\nendstream\n");
    if ($declaredLength === false) {
        throw new RuntimeException('Focused declared-Length stack fixture must expose a fake endstream boundary.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length {$declaredLength} /Filter [ /ASCII85Decode /FlateDecode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseFlateFirstPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused Flate-first fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Flate First Stack Before) Tj ET\n";
    while (strlen($before) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Flate First Stack After) Tj ET";
    $encodedAscii85 = $parserStreamFilterStackBoundaryCurrentBaseAscii85($before . $fakeEndstreamBytes . $after) . '~>';
    if (!str_contains($encodedAscii85, 'endstream!')) {
        throw new RuntimeException('Focused Flate-first stack fixture must contain the fake endstream marker.');
    }

    $encodedAscii85 = str_replace('endstream!', "\nendstream\n!", $encodedAscii85);
    $compressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($encodedAscii85);
    if (!str_contains($compressed, "\nendstream\n!")) {
        throw new RuntimeException('Focused Flate-first stack fixture must expose a fake compressed endstream boundary.');
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /FlateDecode /ASCII85Decode ] >>\nstream\n{$compressed}\nendstream\nendobj\n"
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
    'uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseStackedPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseStackedPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Stacked ASCII85 Flate Before', 'Stacked ASCII85 Flate After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Stacked ASCII85 Flate Before\nStacked ASCII85 Flate After", $text);
        $t->same("Stacked ASCII85 Flate Before\nStacked ASCII85 Flate After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\xd6\x6c\x4a\xc5"));
    },
    'uses the complete filter stack when declared Length points at an encoded fake endstream boundary' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseStaleLengthPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseStaleLengthPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Declared Length Stack Before', 'Declared Length Stack After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Declared Length Stack Before\nDeclared Length Stack After", $text);
        $t->same("Declared Length Stack Before\nDeclared Length Stack After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\xd6\x6c\x4a\xc5"));
    },
    'uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseFlateFirstPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseFlateFirstPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Flate First Stack Before', 'Flate First Stack After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Flate First Stack Before\nFlate First Stack After", $text);
        $t->same("Flate First Stack Before\nFlate First Stack After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, "\xd6\x6c\x4a\xc5"));
    },
];
