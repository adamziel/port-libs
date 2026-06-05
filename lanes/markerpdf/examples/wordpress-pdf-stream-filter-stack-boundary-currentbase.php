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

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$stackLines = $extractor->extractTextLines($stackPdf);
$declaredLengthLines = $extractor->extractTextLines($declaredLengthPdf);
$shortLengthLines = $extractor->extractTextLines($shortLengthPdf);
$flateFirstLines = $extractor->extractTextLines($flateFirstPdf);
$runLengthLines = $extractor->extractTextLines($runLengthPdf);
$runLengthDeclaredLines = $extractor->extractTextLines($runLengthDeclaredPdf);
$nullFilterDecodeParmsLines = $extractor->extractTextLines($nullFilterDecodeParmsPdf);
$compactDecodeParmsLines = $extractor->extractTextLines($compactDecodeParmsPdf);
$strayDecodeParmsLines = $extractor->extractTextLines($strayDecodeParmsPdf);
$allLines = [
    ...$lines,
    ...$stackLines,
    ...$declaredLengthLines,
    ...$shortLengthLines,
    ...$flateFirstLines,
    ...$runLengthLines,
    ...$runLengthDeclaredLines,
    ...$nullFilterDecodeParmsLines,
    ...$compactDecodeParmsLines,
    ...$strayDecodeParmsLines,
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
        ['RunLengthDecode', 'FlateDecode'],
        ['RunLengthDecode', 'FlateDecode'],
        [null, 'FlateDecode'],
        [null, 'ASCII85Decode', 'FlateDecode'],
        [],
    ],
    'singleton_decodeparms_after_null_filter_stack_entry' => true,
    'compact_decodeparms_ignore_null_filter_placeholders' => $compactDecodeParmsLines === [
        'Compact Params Stack Before',
        'Compact Params Stack After',
    ],
    'stray_decodeparms_without_filter_ignored' => $strayDecodeParmsLines === [
        'Stray DecodeParms Visible',
        'Unfiltered Stream Preserved',
    ],
    'missing_length_stream_payload' => true,
    'declared_length_points_at_encoded_fake_endstream' => true,
    'short_declared_length_before_encoded_fake_endstream' => true,
    'declared_length_points_at_runlength_fake_endstream' => true,
    'requires_ascii85_eod_before_endstream_boundary' => true,
    'requires_runlength_eod_before_endstream_boundary' => true,
    'requires_complete_filter_stack_before_boundary' => true,
    'requires_flate_stage_before_ascii85_eod_boundary' => true,
    'fake_endstream_payload_excluded' => !str_contains($joined, 'endstream'),
    'stray_decodeparms_helper_excluded' => !str_contains($joined, 'Stray DecodeParms Helper Leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'paragraphs' => $allLines,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($allLines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
