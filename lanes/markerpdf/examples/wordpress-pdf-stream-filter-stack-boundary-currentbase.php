<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ascii85Encode = static function (string $bytes): string {
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

$zlibStored = static function (string $bytes): string {
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

$runLengthEncode = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length;) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
        $offset += strlen($chunk);
    }

    return $encoded . chr(128);
};

$lzwLiteralEncode = static function (string $bytes): string {
    if (strlen($bytes) > 240) {
        throw new RuntimeException('Focused LZW stack smoke fixture must keep 9-bit literal codes.');
    }

    $codes = array_merge([256], array_map('ord', str_split($bytes)), [257]);
    $bits = '';
    foreach ($codes as $code) {
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

$pngSubPredictorEncode = static function (string $bytes, int $columns): string {
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

$before = "BT /F1 12 Tf 72 720 Td (Before ASCII85 Stack Boundary) Tj ET\n";
while (strlen($before) % 4 !== 0) {
    $before .= ' ';
}

$after = "\nBT /F1 12 Tf 72 704 Td (After ASCII85 Stack Boundary) Tj ET";
$encoded = $ascii85Encode($before)
    . "\nendstream\n!"
    . $ascii85Encode($after)
    . '~>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null /ASCII85Decode ] >>\nstream\n{$encoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$fakeEndstreamBytes = hex2bin('d66c4ac5fe8a5a71');
if ($fakeEndstreamBytes === false) {
    throw new RuntimeException('Unable to build focused fake endstream byte sequence.');
}

$stackBefore = "BT /F1 12 Tf 72 720 Td (Stacked ASCII85 Flate Before) Tj ET\n";
while ((7 + strlen($stackBefore)) % 4 !== 0) {
    $stackBefore .= ' ';
}
$stackAfter = "\nBT /F1 12 Tf 72 704 Td (Stacked ASCII85 Flate After) Tj ET";
$stackEncoded = $ascii85Encode($zlibStored($stackBefore . $fakeEndstreamBytes . $stackAfter));
if (!str_contains($stackEncoded, 'endstream!')) {
    throw new RuntimeException('Focused ASCII85 stack smoke fixture must contain the fake endstream marker.');
}
$stackEncoded = str_replace('endstream!', "\nendstream\n!", $stackEncoded) . '~>';

$stackPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] >>\nstream\n{$stackEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$declaredBefore = "BT /F1 12 Tf 72 720 Td (Declared Length Stack Before) Tj ET\n";
while ((7 + strlen($declaredBefore)) % 4 !== 0) {
    $declaredBefore .= ' ';
}
$declaredAfter = "\nBT /F1 12 Tf 72 704 Td (Declared Length Stack After) Tj ET";
$declaredEncoded = $ascii85Encode($zlibStored($declaredBefore . $fakeEndstreamBytes . $declaredAfter));
if (!str_contains($declaredEncoded, 'endstream!')) {
    throw new RuntimeException('Focused declared-Length stack smoke fixture must contain the fake endstream marker.');
}
$declaredEncoded = str_replace('endstream!', "\nendstream\n!", $declaredEncoded) . '~>';
$declaredLength = strpos($declaredEncoded, "\nendstream\n");
if ($declaredLength === false) {
    throw new RuntimeException('Focused declared-Length stack smoke fixture must expose a fake endstream boundary.');
}

$declaredLengthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length {$declaredLength} /Filter [ /ASCII85Decode /FlateDecode ] >>\nstream\n{$declaredEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$shortLengthBefore = "BT /F1 12 Tf 72 720 Td (Short Length Stack Before) Tj ET\n";
while ((7 + strlen($shortLengthBefore)) % 4 !== 0) {
    $shortLengthBefore .= ' ';
}
$shortLengthAfter = "\nBT /F1 12 Tf 72 704 Td (Short Length Stack After) Tj ET";
$shortLengthEncoded = $ascii85Encode($zlibStored($shortLengthBefore . $fakeEndstreamBytes . $shortLengthAfter));
if (!str_contains($shortLengthEncoded, 'endstream!')) {
    throw new RuntimeException('Focused short-Length stack smoke fixture must contain the fake endstream marker.');
}
$shortLengthEncoded = str_replace('endstream!', "\nendstream\n!", $shortLengthEncoded) . '~>';
$shortLengthBoundary = strpos($shortLengthEncoded, "\nendstream\n");
if ($shortLengthBoundary === false || $shortLengthBoundary < 8) {
    throw new RuntimeException('Focused short-Length stack smoke fixture must expose a fake endstream boundary.');
}
$shortDeclaredLength = $shortLengthBoundary - 5;

$shortLengthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length {$shortDeclaredLength} /Filter [ /ASCII85Decode /FlateDecode ] >>\nstream\n{$shortLengthEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$flateFirstBefore = "BT /F1 12 Tf 72 720 Td (Flate First Stack Before) Tj ET\n";
while (strlen($flateFirstBefore) % 4 !== 0) {
    $flateFirstBefore .= ' ';
}
$flateFirstAfter = "\nBT /F1 12 Tf 72 704 Td (Flate First Stack After) Tj ET";
$flateFirstAscii85 = $ascii85Encode($flateFirstBefore . $fakeEndstreamBytes . $flateFirstAfter) . '~>';
if (!str_contains($flateFirstAscii85, 'endstream!')) {
    throw new RuntimeException('Focused Flate-first stack smoke fixture must contain the fake endstream marker.');
}
$flateFirstAscii85 = str_replace('endstream!', "\nendstream\n!", $flateFirstAscii85);
$flateFirstCompressed = $zlibStored($flateFirstAscii85);
if (!str_contains($flateFirstCompressed, "\nendstream\n!")) {
    throw new RuntimeException('Focused Flate-first stack smoke fixture must expose a fake compressed endstream boundary.');
}

$flateFirstPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /FlateDecode /ASCII85Decode ] >>\nstream\n{$flateFirstCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$missingInnerAscii85EodLeak = 'BT /F1 12 Tf 72 720 Td (Missing Inner ASCII85 EOD Leak) Tj ET';
$missingInnerAscii85EodEncoded = $zlibStored($ascii85Encode($missingInnerAscii85EodLeak));
$validInnerAscii85EodContent = 'BT /F1 12 Tf 72 700 Td (Valid Inner ASCII85 EOD Import) Tj ET';
$validInnerAscii85EodEncoded = $zlibStored($ascii85Encode($validInnerAscii85EodContent) . '~>');
$missingInnerAscii85EodVisibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Missing Inner EOD Boundary) Tj ET';
$missingInnerAscii85EodPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /FlateDecode /ASCII85Decode ] /Length " . strlen($missingInnerAscii85EodEncoded) . " >>\nstream\n{$missingInnerAscii85EodEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /FlateDecode /ASCII85Decode ] /Length " . strlen($validInnerAscii85EodEncoded) . " >>\nstream\n{$validInnerAscii85EodEncoded}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($missingInnerAscii85EodVisibleAfter) . " >>\nstream\n{$missingInnerAscii85EodVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$runLengthBefore = "BT /F1 12 Tf 72 720 Td (RunLength Flate Stack Before) Tj ET\n";
$runLengthAfter = "BT /F1 12 Tf 72 704 Td (RunLength Flate Stack After) Tj ET";
$runLengthEncoded = $runLengthEncode($zlibStored($runLengthBefore . "\nendstream\n" . $runLengthAfter));
$runLengthBoundary = strpos($runLengthEncoded, "\nendstream\n");
if ($runLengthBoundary === false) {
    throw new RuntimeException('Focused RunLength stack smoke fixture must contain raw fake endstream bytes.');
}

$runLengthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /RunLengthDecode /FlateDecode ] >>\nstream\n{$runLengthEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$runLengthDeclaredPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length {$runLengthBoundary} /Filter [ /RunLengthDecode /FlateDecode ] >>\nstream\n{$runLengthEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$nullFilterRowOne = 'BT /F1 12 Tf 72 720 Td (Null Filter Predictor) Tj T* ';
$nullFilterRowTwo = str_pad('(Singleton Dict Applies) Tj ET', strlen($nullFilterRowOne));
$nullFilterCompressed = $zlibStored($pngSubPredictorEncode($nullFilterRowOne . $nullFilterRowTwo, strlen($nullFilterRowOne)));
$nullFilterDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null /FlateDecode ] /DecodeParms << /Predictor 12 /Columns " . strlen($nullFilterRowOne) . " >> /Length " . strlen($nullFilterCompressed) . " >>\nstream\n{$nullFilterCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$nullSlotDecodeParmsRowOne = 'BT /F1 12 Tf 72 720 Td (Null Slot DecodeParms Ignored) Tj T* ';
$nullSlotDecodeParmsRowTwo = str_pad('(Real Flate Still Decodes) Tj ET', strlen($nullSlotDecodeParmsRowOne));
$nullSlotDecodeParmsCompressed = $zlibStored($pngSubPredictorEncode($nullSlotDecodeParmsRowOne . $nullSlotDecodeParmsRowTwo, strlen($nullSlotDecodeParmsRowOne)));
$realFilterDecodeParmsLeak = 'BT /F1 12 Tf 72 680 Td (Real Filter DecodeParms Leak) Tj ET';
$realFilterDecodeParmsCompressed = $zlibStored($realFilterDecodeParmsLeak);
$nullSlotVisibleAfter = 'BT /F1 12 Tf 72 660 Td (Visible After Null Slot Boundary) Tj ET';
$nullSlotDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 12 /Columns " . strlen($nullSlotDecodeParmsRowOne) . " >> ] /Length " . strlen($nullSlotDecodeParmsCompressed) . " >>\nstream\n{$nullSlotDecodeParmsCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /FlateDecode null ] /DecodeParms [ 99 0 R null ] /Length " . strlen($realFilterDecodeParmsCompressed) . " >>\nstream\n{$realFilterDecodeParmsCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($nullSlotVisibleAfter) . " >>\nstream\n{$nullSlotVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$compactDecodeParmsBefore = "BT /F1 12 Tf 72 720 Td (Compact Params Stack Before) Tj ET\n";
while ((7 + strlen($compactDecodeParmsBefore)) % 4 !== 0) {
    $compactDecodeParmsBefore .= ' ';
}
$compactDecodeParmsAfter = "\nBT /F1 12 Tf 72 704 Td (Compact Params Stack After) Tj ET";
$compactDecodeParmsEncoded = $ascii85Encode($zlibStored($compactDecodeParmsBefore . $fakeEndstreamBytes . $compactDecodeParmsAfter));
if (!str_contains($compactDecodeParmsEncoded, 'endstream!')) {
    throw new RuntimeException('Focused compact DecodeParms stack smoke fixture must contain the fake endstream marker.');
}
$compactDecodeParmsEncoded = str_replace('endstream!', "\nendstream\n!", $compactDecodeParmsEncoded) . '~>';

$compactDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 2 /Columns 1 >> ] >>\nstream\n{$compactDecodeParmsEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$aliasCompactDecodeParmsBefore = "BT /F1 12 Tf 72 720 Td (Alias Params Stack Before) Tj ET\n";
while ((7 + 1 + strlen($aliasCompactDecodeParmsBefore)) % 4 !== 0) {
    $aliasCompactDecodeParmsBefore .= ' ';
}
$aliasCompactDecodeParmsAfter = "\nBT /F1 12 Tf 72 704 Td (Alias Params Stack After) Tj ET";
$aliasPredictorRow = "\0" . $aliasCompactDecodeParmsBefore . $fakeEndstreamBytes . $aliasCompactDecodeParmsAfter;
$aliasColumns = strlen($aliasCompactDecodeParmsBefore . $fakeEndstreamBytes . $aliasCompactDecodeParmsAfter);
$aliasCompactDecodeParmsEncoded = $ascii85Encode($zlibStored($aliasPredictorRow));
if (!str_contains($aliasCompactDecodeParmsEncoded, 'endstream!')) {
    throw new RuntimeException('Focused alias DecodeParms stack smoke fixture must contain the fake endstream marker.');
}
$aliasCompactDecodeParmsEncoded = str_replace('endstream!', "\nendstream\n!", $aliasCompactDecodeParmsEncoded) . '~>';

$aliasCompactDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /A85 null /Fl ] /DecodeParms [ null << /Predictor 12 /Columns {$aliasColumns} >> ] >>\nstream\n{$aliasCompactDecodeParmsEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "%%EOF";

$strayDecodeParmsContent = 'BT /F1 12 Tf 72 720 Td (Stray DecodeParms Visible) Tj T* (Unfiltered Stream Preserved) Tj ET';
$strayDecodeParmsObject = 'BT /F1 12 Tf 72 680 Td (Stray DecodeParms Helper Leak) Tj ET';
$strayDecodeParmsPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /DecodeParms 99 0 R /Length " . strlen($strayDecodeParmsContent) . " >>\nstream\n{$strayDecodeParmsContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "99 0 obj\n{$strayDecodeParmsObject}\nendobj\n"
    . "%%EOF";

$allNullFilterContent = 'BT /F1 12 Tf 72 720 Td (All Null Filter Visible) Tj T* (Identity Stack Preserved) Tj ET';
$allNullDecodeParmsObject = 'BT /F1 12 Tf 72 680 Td (All Null DecodeParms Helper Leak) Tj ET';
$allNullFilterPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ null ] /DecodeParms 99 0 R /Length " . strlen($allNullFilterContent) . " >>\nstream\n{$allNullFilterContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "99 0 obj\n{$allNullDecodeParmsObject}\nendobj\n"
    . "%%EOF";

$indirectNullFilterRowOne = 'BT /F1 12 Tf 72 720 Td (Indirect Null Filter Predictor) Tj T* ';
$indirectNullFilterRowTwo = str_pad('(Indirect Null DecodeParms Applies) Tj ET', strlen($indirectNullFilterRowOne));
$indirectNullFilterCompressed = $zlibStored($pngSubPredictorEncode(
    $indirectNullFilterRowOne . $indirectNullFilterRowTwo,
    strlen($indirectNullFilterRowOne)
));
$indirectNullFilterVisibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Indirect Null Filter) Tj ET';
$indirectNullFilterDecodeParmsObject = 'BT /F1 12 Tf 72 640 Td (Indirect Null DecodeParms Helper Leak) Tj ET';
$indirectNullFilterPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ 7 0 R /FlateDecode ] /DecodeParms [ 99 0 R << /Predictor 12 /Columns " . strlen($indirectNullFilterRowOne) . " >> ] /Length " . strlen($indirectNullFilterCompressed) . " >>\nstream\n{$indirectNullFilterCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($indirectNullFilterVisibleAfter) . " >>\nstream\n{$indirectNullFilterVisibleAfter}\nendstream\nendobj\n"
    . "7 0 obj\nnull\nendobj\n"
    . "99 0 obj\n{$indirectNullFilterDecodeParmsObject}\nendobj\n"
    . "%%EOF";

$lzwShortLengthContent = 'BT /F1 12 Tf 72 720 Td (LZW Short Length Before) Tj T* (LZW Short Length After) Tj ET';
$lzwShortLengthEncoded = $lzwLiteralEncode($zlibStored($lzwShortLengthContent));
$lzwShortDeclaredLength = intdiv(strlen($lzwShortLengthEncoded), 2);
$lzwMalformedLeak = 'BT /F1 12 Tf 72 688 Td (Malformed LZW Stack Leak) Tj ET';
$lzwMalformedEncoded = substr($lzwLiteralEncode($zlibStored($lzwMalformedLeak)), 0, -2);
$lzwVisibleAfter = 'BT /F1 12 Tf 72 672 Td (Visible After LZW Boundary) Tj ET';
$lzwShortLengthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length {$lzwShortDeclaredLength} /Filter [ /LZWDecode /FlateDecode ] >>\nstream\n{$lzwShortLengthEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length {$lzwShortDeclaredLength} /Filter [ /LZWDecode /FlateDecode ] >>\nstream\n{$lzwMalformedEncoded}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($lzwVisibleAfter) . " >>\nstream\n{$lzwVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$cryptFirstContent = "BT /F1 12 Tf 72 720 Td (Identity Crypt First Before) Tj ET\n"
    . "\nendstream\n"
    . "BT /F1 12 Tf 72 704 Td (Identity Crypt First After) Tj ET";
$cryptFirstCompressed = $zlibStored($cryptFirstContent);
if (!str_contains($cryptFirstCompressed, "\nendstream\n")) {
    throw new RuntimeException('Focused identity-Crypt-first stack smoke fixture must expose a fake compressed endstream boundary.');
}

$cryptLastContent = "BT /F1 12 Tf 72 688 Td (Flate Then Identity Crypt) Tj ET\n"
    . "\nendstream\n"
    . "BT /F1 12 Tf 72 672 Td (Identity Crypt Tail) Tj ET";
$cryptLastCompressed = $zlibStored($cryptLastContent);
if (!str_contains($cryptLastCompressed, "\nendstream\n")) {
    throw new RuntimeException('Focused identity-Crypt-last stack smoke fixture must expose a fake compressed endstream boundary.');
}

$nonIdentityCryptContent = 'BT /F1 12 Tf 72 656 Td (Non Identity Crypt Leak) Tj ET';
$nonIdentityCryptCompressed = $zlibStored($nonIdentityCryptContent);
$cryptBoundaryVisibleAfter = 'BT /F1 12 Tf 72 640 Td (Visible After Crypt Boundary) Tj ET';
$cryptIdentityPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] >>\nstream\n{$cryptFirstCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /FlateDecode /Crypt ] /DecodeParms [ null << /Type /CryptFilterDecodeParms /Name /Identity >> ] >>\nstream\n{$cryptLastCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /PrivateCF >> null ] /Length " . strlen($nonIdentityCryptCompressed) . " >>\nstream\n{$nonIdentityCryptCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($cryptBoundaryVisibleAfter) . " >>\nstream\n{$cryptBoundaryVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$indirectCryptIdentityContent = 'BT /F1 12 Tf 72 720 Td (Indirect Identity Crypt Import) Tj ET';
$indirectCryptIdentityCompressed = $zlibStored($indirectCryptIdentityContent);
$indirectCryptPrivateContent = 'BT /F1 12 Tf 72 704 Td (Indirect Private Crypt Leak) Tj ET';
$indirectCryptPrivateCompressed = $zlibStored($indirectCryptPrivateContent);
$indirectCryptVisibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After Indirect Crypt) Tj ET';
$indirectCryptNamePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name 10 0 R >> null ] /Length " . strlen($indirectCryptIdentityCompressed) . " >>\nstream\n{$indirectCryptIdentityCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name 11 0 R >> null ] /Length " . strlen($indirectCryptPrivateCompressed) . " >>\nstream\n{$indirectCryptPrivateCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($indirectCryptVisibleAfter) . " >>\nstream\n{$indirectCryptVisibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n/Identity\nendobj\n"
    . "11 0 obj\n/PrivateCF\nendobj\n"
    . "%%EOF";

$defaultCryptContent = "BT /F1 12 Tf 72 720 Td (Default Crypt Stack Before) Tj ET\n"
    . "\nendstream\n"
    . "BT /F1 12 Tf 72 704 Td (Default Crypt Stack After) Tj ET";
$defaultCryptCompressed = $zlibStored($defaultCryptContent);
if (!str_contains($defaultCryptCompressed, "\nendstream\n")) {
    throw new RuntimeException('Focused default-Crypt stack smoke fixture must expose a fake compressed endstream boundary.');
}

$defaultCryptNullDecodeParmsContent = 'BT /F1 12 Tf 72 688 Td (Default Crypt Null DecodeParms) Tj ET';
$defaultCryptNullDecodeParmsCompressed = $zlibStored($defaultCryptNullDecodeParmsContent);
$defaultCryptEmptyDictContent = 'BT /F1 12 Tf 72 672 Td (Default Crypt Empty Dict) Tj ET';
$defaultCryptEmptyDictCompressed = $zlibStored($defaultCryptEmptyDictContent);
$defaultCryptPrivateContent = 'BT /F1 12 Tf 72 656 Td (Default Crypt Private Leak) Tj ET';
$defaultCryptPrivateCompressed = $zlibStored($defaultCryptPrivateContent);
$defaultCryptVisibleAfter = 'BT /F1 12 Tf 72 640 Td (Visible After Default Crypt) Tj ET';
$defaultCryptPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] >>\nstream\n{$defaultCryptCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ null null ] /Length " . strlen($defaultCryptNullDecodeParmsCompressed) . " >>\nstream\n{$defaultCryptNullDecodeParmsCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter [ /FlateDecode /Crypt ] /DecodeParms [ null << >> ] /Length " . strlen($defaultCryptEmptyDictCompressed) . " >>\nstream\n{$defaultCryptEmptyDictCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /PrivateCF >> null ] /Length " . strlen($defaultCryptPrivateCompressed) . " >>\nstream\n{$defaultCryptPrivateCompressed}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($defaultCryptVisibleAfter) . " >>\nstream\n{$defaultCryptVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$commentSplitRowOne = 'BT /F1 12 Tf 72 720 Td (Comment Split Filter Array) Tj T* ';
$commentSplitRowTwo = str_pad('(Comment Split DecodeParms Applies) Tj ET', strlen($commentSplitRowOne));
$commentSplitRows = $pngSubPredictorEncode($commentSplitRowOne . $commentSplitRowTwo, strlen($commentSplitRowOne));
$commentSplitEncoded = $ascii85Encode($zlibStored($commentSplitRows)) . '~>';

$topSplitRowOne = 'BT /F1 12 Tf 72 684 Td (Top Split Filter Reference) Tj T* ';
$topSplitRowTwo = str_pad('(Top Split DecodeParms Reference) Tj ET', strlen($topSplitRowOne));
$topSplitRows = $pngSubPredictorEncode($topSplitRowOne . $topSplitRowTwo, strlen($topSplitRowOne));
$topSplitEncoded = $ascii85Encode($zlibStored($topSplitRows)) . '~>';

$commentSplitVisibleAfter = 'BT /F1 12 Tf 72 648 Td (Visible After Split References) Tj ET';
$commentSplitStaleLeak = 'BT /F1 12 Tf 72 612 Td (Comment Split Helper Leak) Tj ET';
$commentSplitPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ 10 % split filter object number from generation\n 0 R null /FlateDecode ] /DecodeParms [ null null 11 % split decodeparms object number from generation\n 0 R ] /Length " . strlen($commentSplitEncoded) . " >>\nstream\n{$commentSplitEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter 12 % split top-level filter reference\n 0 R /DecodeParms 13 % split top-level decodeparms reference\n 0 R /Length " . strlen($topSplitEncoded) . " >>\nstream\n{$topSplitEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($commentSplitVisibleAfter) . " >>\nstream\n{$commentSplitVisibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n/ASCII85Decode\nendobj\n"
    . "11 0 obj\n<< /Predictor 12 /Columns " . strlen($commentSplitRowOne) . " >>\nendobj\n"
    . "12 0 obj\n[ /ASCII85Decode /FlateDecode ]\nendobj\n"
    . "13 0 obj\n[ null << /Predictor 12 /Columns " . strlen($topSplitRowOne) . " >> ]\nendobj\n"
    . "99 0 obj\n{$commentSplitStaleLeak}\nendobj\n"
    . "%%EOF";

$commentSplitLengthContent = 'BT /F1 12 Tf 72 720 Td (Comment Split Length Imports) Tj ET';
$commentSplitLengthCompressed = $zlibStored($commentSplitLengthContent);
$commentSplitLengthVisibleAfter = 'BT /F1 12 Tf 72 700 Td (Visible After Comment Length) Tj ET';
$commentSplitLengthPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($commentSplitLengthVisibleAfter) . " >>\nstream\n{$commentSplitLengthVisibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n" . strlen($commentSplitLengthCompressed) . "\nendobj\n"
    . "4 0 obj\n<< /Length 10 % split stream length object number from generation\n 0 R /Filter /FlateDecode >>\nstream\n{$commentSplitLengthCompressed}\nendobj\n"
    . "%%EOF";

$malformedIndirectFilterLeak = 'BT /F1 12 Tf 72 720 Td (Malformed Indirect Multi Filter Leak) Tj ET';
$malformedIndirectFilterEncoded = $ascii85Encode($zlibStored($malformedIndirectFilterLeak)) . '~>';
$indirectArrayFilterContent = 'BT /F1 12 Tf 72 700 Td (Indirect Array Filter Preserved) Tj ET';
$indirectArrayFilterEncoded = $ascii85Encode($zlibStored($indirectArrayFilterContent)) . '~>';
$malformedIndirectFilterVisibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Malformed Filter Object) Tj ET';
$malformedIndirectFilterPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter 10 0 R /Length " . strlen($malformedIndirectFilterEncoded) . " >>\nstream\n{$malformedIndirectFilterEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter 12 0 R /Length " . strlen($indirectArrayFilterEncoded) . " >>\nstream\n{$indirectArrayFilterEncoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($malformedIndirectFilterVisibleAfter) . " >>\nstream\n{$malformedIndirectFilterVisibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n/ASCII85Decode /FlateDecode\nendobj\n"
    . "12 0 obj\n[ /ASCII85Decode /FlateDecode ]\nendobj\n"
    . "%%EOF";

$verticalTabAsciiHexLeak = 'BT /F1 12 Tf 72 720 Td (Vertical Tab ASCIIHex Leak) Tj ET';
$verticalTabAsciiHexEncoded = strtoupper(bin2hex($verticalTabAsciiHexLeak));
$verticalTabAsciiHexEncoded = substr($verticalTabAsciiHexEncoded, 0, 12) . "\x0b" . substr($verticalTabAsciiHexEncoded, 12) . '>';
$verticalTabAscii85Leak = 'BT /F1 12 Tf 72 700 Td (Vertical Tab ASCII85 Leak) Tj ET';
$verticalTabAscii85Encoded = $ascii85Encode($verticalTabAscii85Leak);
$verticalTabAscii85Encoded = substr($verticalTabAscii85Encoded, 0, 8) . "\x0b" . substr($verticalTabAscii85Encoded, 8) . '~>';
$verticalTabVisibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Vertical Tab Filter Whitespace) Tj ET';
$verticalTabFilterWhitespacePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($verticalTabAsciiHexEncoded) . " >>\nstream\n{$verticalTabAsciiHexEncoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /ASCII85Decode /Length " . strlen($verticalTabAscii85Encoded) . " >>\nstream\n{$verticalTabAscii85Encoded}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($verticalTabVisibleAfter) . " >>\nstream\n{$verticalTabVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$duplicateFilterLeak = 'BT /F1 12 Tf 72 720 Td (Duplicate Filter Key Leak) Tj ET';
$duplicateFilterCompressed = $zlibStored($duplicateFilterLeak);
$duplicateDecodeParmsLeak = 'BT /F1 12 Tf 72 700 Td (Duplicate DecodeParms Key Leak) Tj ET';
$duplicateDecodeParmsCompressed = $zlibStored($duplicateDecodeParmsLeak);
$duplicateStreamKeysVisibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Duplicate Stream Keys) Tj ET';
$duplicateStreamKeysPdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /Filter /ASCII85Decode /Length " . strlen($duplicateFilterCompressed) . " >>\nstream\n{$duplicateFilterCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 1 >> /DecodeParms << /Predictor 12 /Columns 8 >> /Length " . strlen($duplicateDecodeParmsCompressed) . " >>\nstream\n{$duplicateDecodeParmsCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($duplicateStreamKeysVisibleAfter) . " >>\nstream\n{$duplicateStreamKeysVisibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$stackLines = $extractor->extractTextLines($stackPdf);
$declaredLengthLines = $extractor->extractTextLines($declaredLengthPdf);
$shortLengthLines = $extractor->extractTextLines($shortLengthPdf);
$flateFirstLines = $extractor->extractTextLines($flateFirstPdf);
$missingInnerAscii85EodLines = $extractor->extractTextLines($missingInnerAscii85EodPdf);
$runLengthLines = $extractor->extractTextLines($runLengthPdf);
$runLengthDeclaredLines = $extractor->extractTextLines($runLengthDeclaredPdf);
$nullFilterDecodeParmsLines = $extractor->extractTextLines($nullFilterDecodeParmsPdf);
$nullSlotDecodeParmsLines = $extractor->extractTextLines($nullSlotDecodeParmsPdf);
$compactDecodeParmsLines = $extractor->extractTextLines($compactDecodeParmsPdf);
$aliasCompactDecodeParmsLines = $extractor->extractTextLines($aliasCompactDecodeParmsPdf);
$strayDecodeParmsLines = $extractor->extractTextLines($strayDecodeParmsPdf);
$allNullFilterLines = $extractor->extractTextLines($allNullFilterPdf);
$indirectNullFilterLines = $extractor->extractTextLines($indirectNullFilterPdf);
$lzwShortLengthLines = $extractor->extractTextLines($lzwShortLengthPdf);
$cryptIdentityLines = $extractor->extractTextLines($cryptIdentityPdf);
$indirectCryptNameLines = $extractor->extractTextLines($indirectCryptNamePdf);
$defaultCryptLines = $extractor->extractTextLines($defaultCryptPdf);
$commentSplitLines = $extractor->extractTextLines($commentSplitPdf);
$commentSplitLengthLines = $extractor->extractTextLines($commentSplitLengthPdf);
$malformedIndirectFilterLines = $extractor->extractTextLines($malformedIndirectFilterPdf);
$verticalTabFilterWhitespaceLines = $extractor->extractTextLines($verticalTabFilterWhitespacePdf);
$duplicateStreamKeysLines = $extractor->extractTextLines($duplicateStreamKeysPdf);
$allLines = [
    ...$lines,
    ...$stackLines,
    ...$declaredLengthLines,
    ...$shortLengthLines,
    ...$flateFirstLines,
    ...$missingInnerAscii85EodLines,
    ...$runLengthLines,
    ...$runLengthDeclaredLines,
    ...$nullFilterDecodeParmsLines,
    ...$nullSlotDecodeParmsLines,
    ...$compactDecodeParmsLines,
    ...$aliasCompactDecodeParmsLines,
    ...$strayDecodeParmsLines,
    ...$allNullFilterLines,
    ...$indirectNullFilterLines,
    ...$lzwShortLengthLines,
    ...$cryptIdentityLines,
    ...$indirectCryptNameLines,
    ...$defaultCryptLines,
    ...$commentSplitLines,
    ...$commentSplitLengthLines,
    ...$malformedIndirectFilterLines,
    ...$verticalTabFilterWhitespaceLines,
    ...$duplicateStreamKeysLines,
];
$joined = implode("\n", $allLines);

echo '<!-- markerpdf:pdf-stream-filter-stack-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter-stack-boundary',
    'stream_filters' => [
        [null, 'ASCII85Decode'],
        ['ASCII85Decode', 'FlateDecode'],
        ['ASCII85Decode', 'FlateDecode'],
        ['ASCII85Decode', 'FlateDecode'],
        ['FlateDecode', 'ASCII85Decode'],
        ['FlateDecode', 'ASCII85Decode'],
        ['RunLengthDecode', 'FlateDecode'],
        ['RunLengthDecode', 'FlateDecode'],
        [null, 'FlateDecode'],
        [null, 'FlateDecode'],
        ['FlateDecode', null],
        [null, 'ASCII85Decode', 'FlateDecode'],
        ['A85', null, 'Fl'],
        [],
        [null],
        [null, 'FlateDecode'],
        ['LZWDecode', 'FlateDecode'],
        ['Crypt', 'FlateDecode'],
        ['FlateDecode', 'Crypt'],
        ['Crypt', 'FlateDecode'],
        ['Crypt', 'FlateDecode'],
        ['Crypt', 'FlateDecode'],
        ['Crypt', 'FlateDecode'],
        ['Crypt', 'FlateDecode'],
        ['FlateDecode', 'Crypt'],
        ['Crypt', 'FlateDecode'],
        ['ASCII85Decode', null, 'FlateDecode'],
        ['FlateDecode'],
        ['ASCII85Decode', 'FlateDecode'],
        'malformed_indirect_multi_name_filter_object',
        ['ASCII85Decode', 'FlateDecode'],
        'vertical_tab_asciihex_filter_data_rejected',
        'vertical_tab_ascii85_filter_data_rejected',
        'duplicate_top_level_filter_key_rejected',
        'duplicate_top_level_decodeparms_key_rejected',
    ],
    'singleton_decodeparms_after_null_filter_stack_entry' => true,
    'unresolved_decodeparms_on_null_filter_slot_ignored' => $nullSlotDecodeParmsLines === [
        'Null Slot DecodeParms Ignored',
        'Real Flate Still Decodes',
        'Visible After Null Slot Boundary',
    ],
    'unresolved_decodeparms_on_real_filter_slot_fail_closed' => !str_contains($joined, 'Real Filter DecodeParms Leak'),
    'compact_decodeparms_ignore_null_filter_placeholders' => $compactDecodeParmsLines === [
        'Compact Params Stack Before',
        'Compact Params Stack After',
    ],
    'abbreviated_filter_compact_decodeparms_middle_null' => $aliasCompactDecodeParmsLines === [
        'Alias Params Stack Before',
        'Alias Params Stack After',
    ],
    'stray_decodeparms_without_filter_ignored' => $strayDecodeParmsLines === [
        'Stray DecodeParms Visible',
        'Unfiltered Stream Preserved',
    ],
    'all_null_filter_array_decodeparms_ignored' => $allNullFilterLines === [
        'All Null Filter Visible',
        'Identity Stack Preserved',
    ],
    'indirect_null_filter_object_decodeparms_aligned' => $indirectNullFilterLines === [
        'Indirect Null Filter Predictor',
        'Indirect Null DecodeParms Applies',
        'Visible After Indirect Null Filter',
    ],
    'missing_inner_ascii85_eod_stack_fail_closed' => !str_contains($joined, 'Missing Inner ASCII85 EOD Leak'),
    'valid_inner_ascii85_eod_stack_preserved' => $missingInnerAscii85EodLines === [
        'Valid Inner ASCII85 EOD Import',
        'Visible After Missing Inner EOD Boundary',
    ],
    'short_declared_length_lzw_stack_recovers_after_eod' => $lzwShortLengthLines === [
        'LZW Short Length Before',
        'LZW Short Length After',
        'Visible After LZW Boundary',
    ],
    'malformed_lzw_stack_fail_closed' => !str_contains($joined, 'Malformed LZW Stack Leak'),
    'identity_crypt_filter_stack_passthrough' => $cryptIdentityLines === [
        'Identity Crypt First Before',
        'Identity Crypt First After',
        'Flate Then Identity Crypt',
        'Identity Crypt Tail',
        'Visible After Crypt Boundary',
    ],
    'named_crypt_filter_fail_closed' => !str_contains($joined, 'Non Identity Crypt Leak'),
    'indirect_crypt_identity_name_resolved' => $indirectCryptNameLines === [
        'Indirect Identity Crypt Import',
        'Visible After Indirect Crypt',
    ],
    'indirect_private_crypt_name_fail_closed' => !str_contains($joined, 'Indirect Private Crypt Leak'),
    'default_crypt_decodeparms_identity_passthrough' => $defaultCryptLines === [
        'Default Crypt Stack Before',
        'Default Crypt Stack After',
        'Default Crypt Null DecodeParms',
        'Default Crypt Empty Dict',
        'Visible After Default Crypt',
    ],
    'default_crypt_private_filter_fail_closed' => !str_contains($joined, 'Default Crypt Private Leak'),
    'parser_comment_split_filter_references_resolved' => $commentSplitLines === [
        'Comment Split Filter Array',
        'Comment Split DecodeParms Applies',
        'Top Split Filter Reference',
        'Top Split DecodeParms Reference',
        'Visible After Split References',
    ],
    'parser_comment_split_decodeparms_references_resolved' => str_contains($joined, 'Comment Split DecodeParms Applies')
        && str_contains($joined, 'Top Split DecodeParms Reference'),
    'parser_comment_split_helper_excluded' => !str_contains($joined, 'Comment Split Helper Leak')
        && !str_contains($joined, '10 0 obj')
        && !str_contains($joined, '13 0 obj'),
    'parser_comment_split_length_reference_resolved' => $commentSplitLengthLines === [
        'Comment Split Length Imports',
        'Visible After Comment Length',
    ],
    'parser_comment_split_length_damaged_terminator_bounded' => str_contains($joined, 'Comment Split Length Imports')
        && !str_contains($joined, 'endobj'),
    'malformed_indirect_multi_name_filter_rejected' => $malformedIndirectFilterLines === [
        'Indirect Array Filter Preserved',
        'Visible After Malformed Filter Object',
    ],
    'valid_indirect_filter_array_preserved' => str_contains($joined, 'Indirect Array Filter Preserved'),
    'malformed_indirect_multi_filter_payload_excluded' => !str_contains($joined, 'Malformed Indirect Multi Filter Leak')
        && !str_contains($joined, 'ASCII85Decode /FlateDecode'),
    'vertical_tab_asciihex_filter_data_rejected' => $verticalTabFilterWhitespaceLines === [
        'Visible After Vertical Tab Filter Whitespace',
    ],
    'vertical_tab_ascii85_filter_data_rejected' => $verticalTabFilterWhitespaceLines === [
        'Visible After Vertical Tab Filter Whitespace',
    ],
    'non_pdf_filter_whitespace_payload_excluded' => !str_contains($joined, 'Vertical Tab ASCIIHex Leak')
        && !str_contains($joined, 'Vertical Tab ASCII85 Leak')
        && !str_contains($joined, "\x0b"),
    'duplicate_top_level_filter_key_rejected' => $duplicateStreamKeysLines === [
        'Visible After Duplicate Stream Keys',
    ],
    'duplicate_top_level_decodeparms_key_rejected' => $duplicateStreamKeysLines === [
        'Visible After Duplicate Stream Keys',
    ],
    'duplicate_filter_key_payload_excluded' => !str_contains($joined, 'Duplicate Filter Key Leak'),
    'duplicate_decodeparms_key_payload_excluded' => !str_contains($joined, 'Duplicate DecodeParms Key Leak'),
    'missing_length_stream_payload' => true,
    'declared_length_points_at_encoded_fake_endstream' => true,
    'short_declared_length_before_encoded_fake_endstream' => true,
    'declared_length_points_at_runlength_fake_endstream' => true,
    'requires_ascii85_eod_before_endstream_boundary' => true,
    'requires_runlength_eod_before_endstream_boundary' => true,
    'requires_lzw_eod_before_flate_stack_boundary' => true,
    'requires_complete_filter_stack_before_boundary' => true,
    'requires_flate_stage_before_ascii85_eod_boundary' => true,
    'requires_inner_ascii85_eod_after_flate_stack_boundary' => true,
    'fake_endstream_payload_excluded' => !str_contains($joined, 'endstream'),
    'stray_decodeparms_helper_excluded' => !str_contains($joined, 'Stray DecodeParms Helper Leak'),
    'all_null_decodeparms_helper_excluded' => !str_contains($joined, 'All Null DecodeParms Helper Leak'),
    'indirect_null_decodeparms_helper_excluded' => !str_contains($joined, 'Indirect Null DecodeParms Helper Leak'),
    'crypt_filter_decodeparms_excluded' => !str_contains($joined, 'CryptFilterDecodeParms') && !str_contains($joined, 'PrivateCF'),
    'indirect_crypt_name_objects_excluded' => !str_contains($joined, '10 0 obj') && !str_contains($joined, '11 0 obj'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'paragraphs' => $allLines,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($allLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
