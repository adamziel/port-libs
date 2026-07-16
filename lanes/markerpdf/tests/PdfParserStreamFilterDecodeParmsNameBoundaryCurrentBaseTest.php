<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterDecodeParmsNameBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '<~';
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

$parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress = static function (string $content): string {
    $compressed = gzcompress($content);
    if (!is_string($compressed)) {
        throw new RuntimeException('Unable to compress focused stream-filter DecodeParms /Name fixture.');
    }

    return $compressed;
};

$parserStreamFilterDecodeParmsNameBoundaryCurrentBaseDirectPdf = static function () use (
    $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress
): string {
    $flateLeak = 'BT /F1 12 Tf 72 720 Td (DecodeParms Name Flate Leak) Tj ET';
    $flateCompressed = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress($flateLeak);
    $cryptSafe = 'BT /F1 12 Tf 72 704 Td (Crypt Identity DecodeParms Name Preserved) Tj ET';
    $cryptCompressed = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress($cryptSafe);
    $visibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After DecodeParms Name Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Name /Identity >> /Length " . strlen($flateCompressed) . " >>\nstream\n{$flateCompressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name /Identity >> null ] /Length " . strlen($cryptCompressed) . " >>\nstream\n{$cryptCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

$parserStreamFilterDecodeParmsNameBoundaryCurrentBaseIndirectPdf = static function () use (
    $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseAscii85,
    $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress
): string {
    $safeDefault = 'BT /F1 12 Tf 72 720 Td (Default DecodeParms Still Imports) Tj ET';
    $safeCompressed = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress($safeDefault);
    $indirectLeak = 'BT /F1 12 Tf 72 704 Td (Indirect DecodeParms Name Leak) Tj ET';
    $indirectCompressed = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress($indirectLeak);
    $stackLeak = 'BT /F1 12 Tf 72 688 Td (Stacked DecodeParms Name Leak) Tj ET';
    $stackEncoded = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseAscii85(
        $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseCompress($stackLeak)
    );
    $visibleAfter = 'BT /F1 12 Tf 72 672 Td (Visible After Indirect DecodeParms Name Boundary) Tj ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R 12 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor 1 >> /Length " . strlen($safeCompressed) . " >>\nstream\n{$safeCompressed}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms 10 0 R /Length " . strlen($indirectCompressed) . " >>\nstream\n{$indirectCompressed}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null 11 0 R ] /Length " . strlen($stackEncoded) . " >>\nstream\n{$stackEncoded}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Name /Identity >>\nendobj\n"
        . "11 0 obj\n<< /Name /Identity >>\nendobj\n"
        . "12 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects Crypt-only DecodeParms Name on non-Crypt page content filters' => static function (TestRunner $t) use ($parserStreamFilterDecodeParmsNameBoundaryCurrentBaseDirectPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseDirectPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Crypt Identity DecodeParms Name Preserved',
            'Visible After DecodeParms Name Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'DecodeParms Name Flate Leak'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
    'rejects indirect DecodeParms Name on non-Crypt filter stack entries' => static function (TestRunner $t) use ($parserStreamFilterDecodeParmsNameBoundaryCurrentBaseIndirectPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterDecodeParmsNameBoundaryCurrentBaseIndirectPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Default DecodeParms Still Imports',
            'Visible After Indirect DecodeParms Name Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Indirect DecodeParms Name Leak'));
        $t->true(!str_contains($text, 'Stacked DecodeParms Name Leak'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, '10 0 obj'));
        $t->true(!str_contains($text, '11 0 obj'));
        $t->true(!str_contains($text, "\0"));
    },
];
