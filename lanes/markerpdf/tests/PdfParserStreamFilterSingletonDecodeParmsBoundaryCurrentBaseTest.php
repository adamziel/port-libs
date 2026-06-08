<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$streamFilterSingletonDecodeParmsBoundaryAscii85 = static function (string $bytes): string {
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

$streamFilterSingletonDecodeParmsBoundaryPdf = static function () use (
    $streamFilterSingletonDecodeParmsBoundaryAscii85
): string {
    $ambiguous = 'BT /F1 12 Tf 72 720 Td (Ambiguous Singleton DecodeParms Leak) Tj ET';
    $aligned = 'BT /F1 12 Tf 72 700 Td (Aligned Null DecodeParms Stack Import) Tj ET';
    $visibleAfter = 'BT /F1 12 Tf 72 680 Td (Visible After Singleton DecodeParms Boundary) Tj ET';

    $ambiguousCompressed = gzcompress($ambiguous);
    $alignedCompressed = gzcompress($aligned);
    if (!is_string($ambiguousCompressed) || !is_string($alignedCompressed)) {
        throw new RuntimeException('Unable to compress focused singleton DecodeParms stack fixture.');
    }

    $ambiguousEncoded = $streamFilterSingletonDecodeParmsBoundaryAscii85($ambiguousCompressed);
    $alignedEncoded = $streamFilterSingletonDecodeParmsBoundaryAscii85($alignedCompressed);

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms << /Predictor 1 >> /Length " . strlen($ambiguousEncoded) . " >>\nstream\n{$ambiguousEncoded}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "6 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ null null ] /Length " . strlen($alignedEncoded) . " >>\nstream\n{$alignedEncoded}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'rejects singleton non-null DecodeParms dictionaries on multi-filter stacks before page text import' => static function (
        TestRunner $t
    ) use ($streamFilterSingletonDecodeParmsBoundaryPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $streamFilterSingletonDecodeParmsBoundaryPdf();
        $text = $extractor->extractPlainText($pdf);

        $expected = [
            'Aligned Null DecodeParms Stack Import',
            'Visible After Singleton DecodeParms Boundary',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Ambiguous Singleton DecodeParms Leak'));
        $t->true(!str_contains($text, 'Predictor'));
        $t->true(!str_contains($text, 'ASCII85Decode'));
        $t->true(!str_contains($text, 'FlateDecode'));
        $t->true(!str_contains($text, "\0"));
    },
];
