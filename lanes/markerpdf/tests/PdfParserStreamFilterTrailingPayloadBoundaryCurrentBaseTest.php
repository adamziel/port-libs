<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserStreamFilterTrailingPayloadBoundaryAscii85 = static function (string $bytes): string {
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

$parserStreamFilterTrailingPayloadBoundaryAsciiHex = static function (string $bytes): string {
    return strtoupper(bin2hex($bytes));
};

$parserStreamFilterTrailingPayloadBoundaryContent = static function (string $text, int $y): string {
    return "BT /F1 12 Tf 72 {$y} Td ({$text}) Tj ET";
};

$parserStreamFilterTrailingPayloadBoundaryPdf = static function () use (
    $parserStreamFilterTrailingPayloadBoundaryAscii85,
    $parserStreamFilterTrailingPayloadBoundaryAsciiHex,
    $parserStreamFilterTrailingPayloadBoundaryContent
): string {
    $directLeak = $parserStreamFilterTrailingPayloadBoundaryContent('Unbounded ASCIIHex Content Leak', 720);
    $directEncoded = $parserStreamFilterTrailingPayloadBoundaryAsciiHex($directLeak)
        . '>BT /F1 12 Tf 72 700 Td (Raw Trailing Hex Decoy) Tj ET';

    $stackLeak = $parserStreamFilterTrailingPayloadBoundaryContent('Unbounded ASCII85 Stack Leak', 684);
    $stackCompressed = gzcompress($stackLeak, 0);
    if (!is_string($stackCompressed)) {
        throw new RuntimeException('Unable to compress focused unbounded ASCII85 stack fixture.');
    }
    $stackEncoded = $parserStreamFilterTrailingPayloadBoundaryAscii85($stackCompressed)
        . '~>BT /F1 12 Tf 72 664 Td (Raw Trailing Stack Decoy) Tj ET';

    $boundedStack = $parserStreamFilterTrailingPayloadBoundaryContent('Bounded Stack Content Import', 648);
    $boundedCompressed = gzcompress($boundedStack, 0);
    if (!is_string($boundedCompressed)) {
        throw new RuntimeException('Unable to compress focused bounded ASCII85 stack fixture.');
    }
    $boundedEncoded = $parserStreamFilterTrailingPayloadBoundaryAscii85($boundedCompressed) . "~>\n  \t";

    $visibleAfter = $parserStreamFilterTrailingPayloadBoundaryContent('Visible After Trailing Payload Boundary', 628);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($directEncoded) . " >>\nstream\n{$directEncoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($stackEncoded) . " >>\nstream\n{$stackEncoded}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /Length " . strlen($boundedEncoded) . " >>\nstream\n{$boundedEncoded}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects non-whitespace bytes after explicit filter EOD markers before page text import' => static function (TestRunner $t) use ($parserStreamFilterTrailingPayloadBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserStreamFilterTrailingPayloadBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Bounded Stack Content Import',
            'Visible After Trailing Payload Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Unbounded ASCIIHex Content Leak'));
        $t->true(!str_contains($text, 'Unbounded ASCII85 Stack Leak'));
        $t->true(!str_contains($text, 'Raw Trailing Hex Decoy'));
        $t->true(!str_contains($text, 'Raw Trailing Stack Decoy'));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
