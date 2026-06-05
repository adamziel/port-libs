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

$parserStreamFilterStackBoundaryCurrentBaseRunLength = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $encoded . chr(128);
};

$parserStreamFilterStackBoundaryCurrentBaseLzwLiteral = static function (string $bytes): string {
    if (strlen($bytes) > 240) {
        throw new RuntimeException('Focused LZW stack fixture must keep 9-bit literal codes.');
    }

    $codes = array_merge([256], array_map('ord', str_split($bytes)), [257]);
    $bits = '';
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 511) {
            throw new RuntimeException('Focused LZW stack fixture uses invalid 9-bit code.');
        }

        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        if (strlen($byte) < 8) {
            $byte = str_pad($byte, 8, '0');
        }

        $encoded .= chr(bindec($byte));
    }

    return $encoded;
};

$parserStreamFilterStackBoundaryCurrentBasePngSubPredictor = static function (string $bytes, int $columns): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
        $row = substr($bytes, $offset, $columns);
        if (strlen($row) !== $columns) {
            throw new RuntimeException('Focused null-filter DecodeParms rows must be fixed-width.');
        }

        $encoded .= "\x01";
        for ($index = 0; $index < $columns; $index++) {
            $left = $index > 0 ? ord($row[$index - 1]) : 0;
            $encoded .= chr((ord($row[$index]) - $left) & 0xff);
        }
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

$parserStreamFilterStackBoundaryCurrentBaseNullFilterDecodeParmsPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $rowOne = 'BT /F1 12 Tf 72 720 Td (Null Filter Predictor) Tj T* ';
    $rowTwo = str_pad('(Singleton Dict Applies) Tj ET', strlen($rowOne));
    $encodedRows = $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor($rowOne . $rowTwo, strlen($rowOne));
    $compressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($encodedRows);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ null /FlateDecode ] /DecodeParms << /Predictor 12 /Columns " . strlen($rowOne) . " >> /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseNullDecodeParmsSlotPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $rowOne = 'BT /F1 12 Tf 72 720 Td (Null Slot DecodeParms Ignored) Tj T* ';
    $rowTwo = str_pad('(Real Flate Still Decodes) Tj ET', strlen($rowOne));
    $encodedRows = $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor($rowOne . $rowTwo, strlen($rowOne));
    $compressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($encodedRows);

    $realFilterDecodeParmsLeak = 'BT /F1 12 Tf 72 680 Td (Real Filter DecodeParms Leak) Tj ET';
    $realFilterCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($realFilterDecodeParmsLeak);
    $visibleAfter = 'BT /F1 12 Tf 72 660 Td (Visible After Null Slot Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 12 /Columns " . strlen($rowOne) . " >> ] /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /FlateDecode null ] /DecodeParms [ 99 0 R null ] /Length " . strlen($realFilterCompressed) . " >>\nstream\n{$realFilterCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseCompactDecodeParmsPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused compact DecodeParms fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Compact Params Stack Before) Tj ET\n";
    while ((7 + strlen($before)) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Compact Params Stack After) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($before . $fakeEndstreamBytes . $after)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused compact DecodeParms stack fixture must contain the fake endstream marker.');
    }
    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ null /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 2 /Columns 1 >> ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseAliasCompactDecodeParmsPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused alias DecodeParms fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Alias Params Stack Before) Tj ET\n";
    while ((7 + 1 + strlen($before)) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Alias Params Stack After) Tj ET";
    $predictorRow = "\0" . $before . $fakeEndstreamBytes . $after;
    $columns = strlen($before . $fakeEndstreamBytes . $after);
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($predictorRow)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused alias DecodeParms stack fixture must contain the fake endstream marker.');
    }
    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /A85 null /Fl ] /DecodeParms [ null << /Predictor 12 /Columns {$columns} >> ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseStrayDecodeParmsPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Stray DecodeParms Visible) Tj T* (Unfiltered Stream Preserved) Tj ET';
    $staleDecodeParmsObject = 'BT /F1 12 Tf 72 680 Td (Stray DecodeParms Helper Leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /DecodeParms 99 0 R /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "99 0 obj\n{$staleDecodeParmsObject}\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseAllNullFilterPdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (All Null Filter Visible) Tj T* (Identity Stack Preserved) Tj ET';
    $staleDecodeParmsObject = 'BT /F1 12 Tf 72 680 Td (All Null DecodeParms Helper Leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ null ] /DecodeParms 99 0 R /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "99 0 obj\n{$staleDecodeParmsObject}\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseIndirectNullFilterPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $rowOne = 'BT /F1 12 Tf 72 720 Td (Indirect Null Filter Predictor) Tj T* ';
    $rowTwo = str_pad('(Indirect Null DecodeParms Applies) Tj ET', strlen($rowOne));
    $encodedRows = $parserStreamFilterStackBoundaryCurrentBasePngSubPredictor($rowOne . $rowTwo, strlen($rowOne));
    $compressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($encodedRows);
    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Indirect Null Filter) Tj ET';
    $staleDecodeParmsObject = 'BT /F1 12 Tf 72 640 Td (Indirect Null DecodeParms Helper Leak) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ 7 0 R /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 12 /Columns " . strlen($rowOne) . " >> ] /Length " . strlen($compressed) . " >>\nstream\n{$compressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "7 0 obj\nnull\nendobj\n"
        . "99 0 obj\n{$staleDecodeParmsObject}\nendobj\n"
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

$parserStreamFilterStackBoundaryCurrentBaseShortLengthPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseAscii85,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
    if ($fakeEndstreamBytes === false) {
        throw new RuntimeException('Unable to build focused short-Length fake endstream byte sequence.');
    }

    $before = "BT /F1 12 Tf 72 720 Td (Short Length Stack Before) Tj ET\n";
    while ((7 + strlen($before)) % 4 !== 0) {
        $before .= ' ';
    }

    $after = "\nBT /F1 12 Tf 72 704 Td (Short Length Stack After) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseAscii85(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($before . $fakeEndstreamBytes . $after)
    );
    if (!str_contains($encoded, 'endstream!')) {
        throw new RuntimeException('Focused short-Length stack fixture must contain the fake endstream marker.');
    }

    $encoded = str_replace('endstream!', "\nendstream\n!", $encoded) . '~>';
    $fakeBoundary = strpos($encoded, "\nendstream\n");
    if ($fakeBoundary === false || $fakeBoundary < 8) {
        throw new RuntimeException('Focused short-Length stack fixture must expose a fake endstream boundary.');
    }

    $declaredLength = $fakeBoundary - 5;

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

$parserStreamFilterStackBoundaryCurrentBaseRunLengthPdf = static function (
    ?int &$declaredLength = null
) use (
    $parserStreamFilterStackBoundaryCurrentBaseRunLength,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $before = "BT /F1 12 Tf 72 720 Td (RunLength Flate Stack Before) Tj ET\n";
    $after = "BT /F1 12 Tf 72 704 Td (RunLength Flate Stack After) Tj ET";
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseRunLength(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($before . "\nendstream\n" . $after)
    );
    $fakeBoundary = strpos($encoded, "\nendstream\n");
    if ($fakeBoundary === false) {
        throw new RuntimeException('Focused RunLength stack fixture must contain raw fake endstream bytes.');
    }

    $lengthEntry = '';
    if ($declaredLength !== null) {
        $declaredLength = $fakeBoundary;
        $lengthEntry = "/Length {$fakeBoundary} ";
    }

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< {$lengthEntry}/Filter [ /RunLengthDecode /FlateDecode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseLzwShortLengthPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseLzwLiteral,
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (LZW Short Length Before) Tj T* (LZW Short Length After) Tj ET';
    $encoded = $parserStreamFilterStackBoundaryCurrentBaseLzwLiteral(
        $parserStreamFilterStackBoundaryCurrentBaseZlibStored($content)
    );
    $declaredLength = intdiv(strlen($encoded), 2);
    if ($declaredLength < 1) {
        throw new RuntimeException('Focused LZW stack fixture must have a short declared length.');
    }

    $malformedLeak = 'BT /F1 12 Tf 72 688 Td (Malformed LZW Stack Leak) Tj ET';
    $malformedEncoded = substr(
        $parserStreamFilterStackBoundaryCurrentBaseLzwLiteral(
            $parserStreamFilterStackBoundaryCurrentBaseZlibStored($malformedLeak)
        ),
        0,
        -2
    );
    $visibleAfter = 'BT /F1 12 Tf 72 672 Td (Visible After LZW Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length {$declaredLength} /Filter [ /LZWDecode /FlateDecode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Length {$declaredLength} /Filter [ /LZWDecode /FlateDecode ] >>\nstream\n{$malformedEncoded}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseCryptIdentityPdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $cryptFirstContent = "BT /F1 12 Tf 72 720 Td (Identity Crypt First Before) Tj ET\n"
        . "\nendstream\n"
        . "BT /F1 12 Tf 72 704 Td (Identity Crypt First After) Tj ET";
    $cryptFirstCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($cryptFirstContent);
    if (!str_contains($cryptFirstCompressed, "\nendstream\n")) {
        throw new RuntimeException('Focused identity-Crypt-first stack fixture must expose a fake compressed endstream boundary.');
    }

    $cryptLastContent = "BT /F1 12 Tf 72 688 Td (Flate Then Identity Crypt) Tj ET\n"
        . "\nendstream\n"
        . "BT /F1 12 Tf 72 672 Td (Identity Crypt Tail) Tj ET";
    $cryptLastCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($cryptLastContent);
    if (!str_contains($cryptLastCompressed, "\nendstream\n")) {
        throw new RuntimeException('Focused identity-Crypt-last stack fixture must expose a fake compressed endstream boundary.');
    }

    $nonIdentityContent = 'BT /F1 12 Tf 72 656 Td (Non Identity Crypt Leak) Tj ET';
    $nonIdentityCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($nonIdentityContent);
    $visibleAfter = 'BT /F1 12 Tf 72 640 Td (Visible After Crypt Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] >>\nstream\n{$cryptFirstCompressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /FlateDecode /Crypt ] /DecodeParms [ null << /Type /CryptFilterDecodeParms /Name /Identity >> ] >>\nstream\n{$cryptLastCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /PrivateCF >> null ] /Length " . strlen($nonIdentityCompressed) . " >>\nstream\n{$nonIdentityCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterStackBoundaryCurrentBaseIndirectCryptNamePdf = static function () use (
    $parserStreamFilterStackBoundaryCurrentBaseZlibStored
): string {
    $identityContent = 'BT /F1 12 Tf 72 720 Td (Indirect Identity Crypt Import) Tj ET';
    $identityCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($identityContent);
    $privateContent = 'BT /F1 12 Tf 72 704 Td (Indirect Private Crypt Leak) Tj ET';
    $privateCompressed = $parserStreamFilterStackBoundaryCurrentBaseZlibStored($privateContent);
    $visibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After Indirect Crypt) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name 10 0 R >> null ] /Length " . strlen($identityCompressed) . " >>\nstream\n{$identityCompressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name 11 0 R >> null ] /Length " . strlen($privateCompressed) . " >>\nstream\n{$privateCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "10 0 obj\n/Identity\nendobj\n"
        . "11 0 obj\n/PrivateCF\nendobj\n"
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
    'applies singleton DecodeParms dictionaries to the only real filter after null stack entries' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseNullFilterDecodeParmsPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseNullFilterDecodeParmsPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Null Filter Predictor', 'Singleton Dict Applies'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Null Filter Predictor\nSingleton Dict Applies", $text);
        $t->same("Null Filter Predictor\nSingleton Dict Applies\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'DecodeParms'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
    'ignores unresolved DecodeParms entries aligned to null filters while failing closed on real filters' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseNullDecodeParmsSlotPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseNullDecodeParmsSlotPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Null Slot DecodeParms Ignored', 'Real Flate Still Decodes', 'Visible After Null Slot Boundary'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Null Slot DecodeParms Ignored\nReal Flate Still Decodes\nVisible After Null Slot Boundary", $text);
        $t->same("Null Slot DecodeParms Ignored\nReal Flate Still Decodes\nVisible After Null Slot Boundary\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Real Filter DecodeParms Leak'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
    'aligns compact DecodeParms arrays to real filters after null placeholders in stream stacks' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseCompactDecodeParmsPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseCompactDecodeParmsPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Compact Params Stack Before', 'Compact Params Stack After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Compact Params Stack Before\nCompact Params Stack After", $text);
        $t->same("Compact Params Stack Before\nCompact Params Stack After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, 'Predictor'));
        $t->true(!str_contains($text, "\xd6\x6c\x4a\xc5"));
    },
    'aligns compact DecodeParms arrays to abbreviated filters around middle null placeholders' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseAliasCompactDecodeParmsPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseAliasCompactDecodeParmsPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Alias Params Stack Before', 'Alias Params Stack After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Alias Params Stack Before\nAlias Params Stack After", $text);
        $t->same("Alias Params Stack Before\nAlias Params Stack After\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, 'A85'));
        $t->true(!str_contains($text, 'Fl'));
        $t->true(!str_contains($text, 'Predictor'));
        $t->true(!str_contains($text, "\0"));
        $t->true(!str_contains($text, "\xd6\x6c\x4a\xc5"));
    },
    'ignores stray DecodeParms when no stream filters are declared before WordPress text extraction' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseStrayDecodeParmsPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseStrayDecodeParmsPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Stray DecodeParms Visible', 'Unfiltered Stream Preserved'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Stray DecodeParms Visible\nUnfiltered Stream Preserved", $text);
        $t->same("Stray DecodeParms Visible\nUnfiltered Stream Preserved\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stray DecodeParms Helper Leak'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, "\0"));
    },
    'treats all-null filter arrays as an empty stack before resolving stray DecodeParms' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseAllNullFilterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseAllNullFilterPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['All Null Filter Visible', 'Identity Stack Preserved'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("All Null Filter Visible\nIdentity Stack Preserved", $text);
        $t->same("All Null Filter Visible\nIdentity Stack Preserved\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'All Null DecodeParms Helper Leak'));
        $t->true(!str_contains($text, 'DecodeParms'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, "\0"));
    },
    'treats indirect null filter objects as identity stack slots before DecodeParms alignment' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseIndirectNullFilterPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseIndirectNullFilterPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Indirect Null Filter Predictor',
            'Indirect Null DecodeParms Applies',
            'Visible After Indirect Null Filter',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Indirect Null Filter Predictor\nIndirect Null DecodeParms Applies\nVisible After Indirect Null Filter", $text);
        $t->same("Indirect Null Filter Predictor\nIndirect Null DecodeParms Applies\nVisible After Indirect Null Filter\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Indirect Null DecodeParms Helper Leak'));
        $t->true(!str_contains($text, '99 0 obj'));
        $t->true(!str_contains($text, 'FlateDecode'));
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
    'uses the complete filter stack when stale Length lands before an encoded fake endstream boundary' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseShortLengthPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseShortLengthPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['Short Length Stack Before', 'Short Length Stack After'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Short Length Stack Before\nShort Length Stack After", $text);
        $t->same("Short Length Stack Before\nShort Length Stack After\n", $extractor->naiveGetText($pdf));
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
    'requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseRunLengthPdf): void {
        $extractor = new PdfTextExtractor();
        $declaredLength = 0;
        $missingLengthPdf = $parserStreamFilterStackBoundaryCurrentBaseRunLengthPdf();
        $declaredLengthPdf = $parserStreamFilterStackBoundaryCurrentBaseRunLengthPdf($declaredLength);

        $expected = ['RunLength Flate Stack Before', 'RunLength Flate Stack After'];
        foreach ([$missingLengthPdf, $declaredLengthPdf] as $pdf) {
            $text = $extractor->extractPlainText($pdf);
            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("RunLength Flate Stack Before\nRunLength Flate Stack After", $text);
            $t->same("RunLength Flate Stack Before\nRunLength Flate Stack After\n", $extractor->naiveGetText($pdf));
            $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->same(['1'], $extractor->extractPageLabels($pdf));
            $t->true(!str_contains($text, 'endstream'));
            $t->true(!str_contains($text, 'RunLengthDecode'));
            $t->true(!str_contains($text, 'FlateDecode'));
        }
        $t->true($declaredLength > 0);
    },
    'recovers short Length LZW and Flate stacks only after the LZW EOD code' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseLzwShortLengthPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseLzwShortLengthPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = ['LZW Short Length Before', 'LZW Short Length After', 'Visible After LZW Boundary'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("LZW Short Length Before\nLZW Short Length After\nVisible After LZW Boundary", $text);
        $t->same("LZW Short Length Before\nLZW Short Length After\nVisible After LZW Boundary\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed LZW Stack Leak'));
        $t->true(!str_contains($text, 'LZWDecode'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
    'treats explicit Identity Crypt filters as pass-through stack stages while rejecting named crypt filters' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseCryptIdentityPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseCryptIdentityPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Identity Crypt First Before',
            'Identity Crypt First After',
            'Flate Then Identity Crypt',
            'Identity Crypt Tail',
            'Visible After Crypt Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Non Identity Crypt Leak'));
        $t->true(!str_contains($text, 'PrivateCF'));
        $t->true(!str_contains($text, 'CryptFilterDecodeParms'));
        $t->true(!str_contains($text, 'endstream'));
        $t->true(!str_contains($text, "\0"));
    },
    'resolves indirect Crypt filter names before choosing identity pass-through' => static function (TestRunner $t) use ($parserStreamFilterStackBoundaryCurrentBaseIndirectCryptNamePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterStackBoundaryCurrentBaseIndirectCryptNamePdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Indirect Identity Crypt Import',
            'Visible After Indirect Crypt',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Indirect Private Crypt Leak'));
        $t->true(!str_contains($text, 'PrivateCF'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
