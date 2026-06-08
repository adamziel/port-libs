<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$streamFilterDecodeParmsApplicabilityAscii85 = static function (string $bytes): string {
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

    return $encoded . '~>';
};

$streamFilterDecodeParmsApplicabilityPngSub = static function (string $bytes): string {
    $encoded = "\x01";
    for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
        $left = $index > 0 ? ord($bytes[$index - 1]) : 0;
        $encoded .= chr((ord($bytes[$index]) - $left) & 0xff);
    }

    return $encoded;
};

$streamFilterDecodeParmsApplicabilityPdf = static function () use (
    $streamFilterDecodeParmsApplicabilityAscii85,
    $streamFilterDecodeParmsApplicabilityPngSub
): string {
    $badContent = 'BT /F1 12 Tf 72 720 Td (ASCII85 Predictor Params Leak) Tj ET';
    $badCompressed = gzcompress($badContent, 0);

    $goodContent = 'BT /F1 12 Tf 72 700 Td (Flate Predictor Params Import) Tj ET';
    $goodPredicted = $streamFilterDecodeParmsApplicabilityPngSub($goodContent);
    $goodCompressed = gzcompress($goodPredicted, 0);

    if (!is_string($badCompressed) || !is_string($goodCompressed)) {
        throw new RuntimeException('Unable to compress focused DecodeParms applicability fixture.');
    }

    $badEncoded = $streamFilterDecodeParmsApplicabilityAscii85($badCompressed);
    $goodEncoded = $streamFilterDecodeParmsApplicabilityAscii85($goodCompressed);
    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Filter Applicability) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Columns " . strlen($badContent) . " /Colors 1 /BitsPerComponent 8 >> null ] /Length " . strlen($badEncoded) . " >>\nstream\n{$badEncoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null << /Predictor 12 /Columns " . strlen($goodContent) . " /Colors 1 /BitsPerComponent 8 >> ] /Length " . strlen($goodEncoded) . " >>\nstream\n{$goodEncoded}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects predictor DecodeParms aligned to non-predictor filters before WordPress text import' => static function (
        TestRunner $t
    ) use ($streamFilterDecodeParmsApplicabilityPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $streamFilterDecodeParmsApplicabilityPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Flate Predictor Params Import',
            'Visible After Filter Applicability',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'ASCII85 Predictor Params Leak'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, 'DecodeParms'));
        $t->true(!str_contains($text, 'Columns'));
        $t->true(!str_contains($text, "\0"));
    },
];
