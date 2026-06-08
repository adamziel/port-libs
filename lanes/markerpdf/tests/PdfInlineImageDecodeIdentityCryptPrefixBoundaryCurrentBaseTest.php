<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageCryptPrefixBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$ascii85Encode = static function (string $bytes): string {
    $encoded = '<~';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        $digits = [];
        for ($index = 0; $index < 5; $index++) {
            $digits[] = chr(($value % 85) + 33);
            $value = intdiv($value, 85);
        }

        $encoded .= implode('', array_slice(array_reverse($digits), 0, $chunkLength + 1));
    }

    return $encoded . '~>';
};

$lzwPackCodes = static function (array $codes): string {
    $bits = '';
    foreach ($codes as $code) {
        if (!is_int($code) || $code < 0 || $code > 511) {
            throw new RuntimeException('Focused inline LZW fixture codes must fit the initial code size.');
        }

        for ($shift = 8; $shift >= 0; $shift--) {
            $bits .= (($code >> $shift) & 1) === 1 ? '1' : '0';
        }
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 8) {
        $byte = substr($bits, $offset, 8);
        $encoded .= chr(bindec(str_pad($byte, 8, '0')));
    }

    return $encoded;
};

$lzwLiteralEncode = static fn (string $byte): string => $lzwPackCodes([256, ord($byte), 257]);

$runLengthLiteralEncode = static function (string $bytes): string {
    if ($bytes === '') {
        return chr(128);
    }

    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

return [
    'keeps Identity Crypt prefix native-EOD inline images without sample floors closed until real EI terminators' => static function (TestRunner $t) use (
        $inlineImageCryptPrefixBoundaryPdf,
        $ascii85Encode,
        $lzwLiteralEncode,
        $runLengthLiteralEncode
    ): void {
        $extractor = new PdfTextExtractor();
        $renderer = new PdfImageRenderer();
        $cases = [
            'ASCII85' => [
                '/W 1 /H 1 /F [/Crypt /A85] /DP [<< /Name /Identity >> null]',
                $ascii85Encode('A'),
                ['Crypt', 'ASCII85Decode'],
                'Before Identity Crypt ASCII85 No Floor',
                'After Identity Crypt ASCII85 No Floor',
                'Identity Crypt ASCII85 No Floor Noise',
            ],
            'LZW' => [
                '/W 1 /H 1 /F [/Crypt /LZW] /DP [<< /Name /Identity >> null]',
                $lzwLiteralEncode('B'),
                ['Crypt', 'LZWDecode'],
                'Before Identity Crypt LZW No Floor',
                'After Identity Crypt LZW No Floor',
                'Identity Crypt LZW No Floor Noise',
            ],
            'RunLength' => [
                '/W 1 /H 1 /F [/Crypt /RL] /DP [<< /Name /Identity >> null]',
                $runLengthLiteralEncode('C'),
                ['Crypt', 'RunLengthDecode'],
                'Before Identity Crypt RunLength No Floor',
                'After Identity Crypt RunLength No Floor',
                'Identity Crypt RunLength No Floor Noise',
            ],
        ];

        $content = '';
        $expected = [];
        foreach ($cases as [$dictionary, $payloadPrefix, $filters, $before, $after, $leak]) {
            $payload = $payloadPrefix . "ZZ EI BT /F1 12 Tf 72 690 Td ({$leak}) Tj ET rawtail";
            $content .= "BT /F1 12 Tf 72 720 Td ({$before}) Tj ET\n"
                . "BI {$dictionary} ID {$payload}\nEI\n"
                . "BT /F1 12 Tf 72 704 Td ({$after}) Tj ET\n";
            $expected[] = $before;
            $expected[] = $after;
            $t->true(str_contains($payload, ' EI '));

            $review = $renderer->inlineImageReviewPlan($dictionary, $payloadPrefix, [], 1);
            $t->same($filters, $review['image_filters']);
            $t->same([], $review['image_filter_boundary']['preview_only_filters']);
            $t->same([], $review['inline_image']['unsupported_filters']);
            $t->true($review['inline_image']['native_raster_decode']);
            $t->true($review['inline_image']['excluded_from_visible_text']);
        }

        $pdf = $inlineImageCryptPrefixBoundaryPdf($content);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $plainText);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        foreach ($cases as [, , , , , $leak]) {
            $t->true(!str_contains($plainText, $leak));
        }
        $t->true(!str_contains($plainText, 'ZZ EI'));
        $t->true(!str_contains($plainText, 'rawtail'));
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
    },
];
