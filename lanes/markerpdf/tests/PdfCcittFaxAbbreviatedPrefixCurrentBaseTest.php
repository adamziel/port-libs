<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$ccittFaxAbbrevPrefixZlibStored = static function (string $bytes): string {
    $length = strlen($bytes);
    if ($length > 65535) {
        throw new RuntimeException('Focused CCITT abbreviation fixture must fit one deflate stored block.');
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

$ccittFaxAbbrevPrefixRunLengthEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$ccittFaxAbbrevPrefixPayloads = static function () use (
    $ccittFaxAbbrevPrefixZlibStored,
    $ccittFaxAbbrevPrefixRunLengthEncode
): array {
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (Abbreviated CCITT native prefix leak) Tj ET';
    $eofb = "\x00\x10\x01";
    $nativeBytes = "\x11\x22\x33\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . "\x44\x55{$eofb}";
    $runLengthStream = $ccittFaxAbbrevPrefixRunLengthEncode($nativeBytes);
    $flateStream = $ccittFaxAbbrevPrefixZlibStored($nativeBytes);
    $runLengthStaleLength = strpos($runLengthStream, "\nendstream\n");
    $flateStaleLength = strpos($flateStream, "\nendstream\n");
    if ($runLengthStaleLength === false || $flateStaleLength === false) {
        throw new RuntimeException('Focused CCITT abbreviation fixture must expose stale raw stream markers.');
    }

    return [
        'AHx' => [
            'canonical' => 'ASCIIHexDecode',
            'stream' => strtoupper(bin2hex($nativeBytes)) . '>',
            'native_bytes' => $nativeBytes,
            'declared_length' => null,
        ],
        'RL' => [
            'canonical' => 'RunLengthDecode',
            'stream' => $runLengthStream,
            'native_bytes' => $nativeBytes,
            'declared_length' => $runLengthStaleLength,
        ],
        'Fl' => [
            'canonical' => 'FlateDecode',
            'stream' => $flateStream,
            'native_bytes' => $nativeBytes,
            'declared_length' => $flateStaleLength,
        ],
    ];
};

return [
    'exposes canonical native-prefix metadata for abbreviated CCITT XObject filters' => static function (
        TestRunner $t
    ) use ($ccittFaxAbbrevPrefixPayloads): void {
        $renderer = new PdfImageRenderer();

        foreach ($ccittFaxAbbrevPrefixPayloads() as $declaredFilter => $case) {
            $plan = $renderer->imageColorSpaceSoftMaskPlan(
                '<< /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 '
                    . "/Filter [/{$declaredFilter} /CCF] "
                    . '/DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] >>'
            );
            $boundary = $plan['ccitt_fax_filter_boundary'] ?? [];

            $t->same([$declaredFilter, 'CCF'], $plan['image_filters']);
            $t->same(['CCF'], $plan['image_filter_boundary']['preview_only_filters']);
            $t->same($declaredFilter, $boundary['native_prefix_filters'][0] ?? null);
            $t->same($case['canonical'], $boundary['canonical_native_prefix_filters'][0] ?? null);
            $t->same('CCF', $boundary['declared_filter'] ?? null);
            $t->same('CCITTFaxDecode', $boundary['canonical_filter'] ?? null);
            $t->same(true, $boundary['alias_used'] ?? null);
            $t->same(true, $boundary['source_filter_preserved'] ?? null);
            $t->same(false, $boundary['native_raster_decode'] ?? null);
        }
    },
    'keeps abbreviated native-prefix CCITT streams owned until the CCF image boundary' => static function (
        TestRunner $t
    ) use ($ccittFaxAbbrevPrefixPayloads): void {
        $extractor = new PdfTextExtractor();

        foreach ($ccittFaxAbbrevPrefixPayloads() as $declaredFilter => $case) {
            $before = "BT /F1 12 Tf 72 720 Td (Before {$declaredFilter} CCF import) Tj ET";
            $after = "BT /F1 12 Tf 72 680 Td (After {$declaredFilter} CCF import) Tj ET";
            $stream = $case['stream'];
            $nativeBytes = $case['native_bytes'];
            $declaredLength = $case['declared_length'] ?? strlen($stream);

            $pdf = "%PDF-1.4\n"
                . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
                . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /AbbrevFax 5 0 R >> >> >>\nendobj\n"
                . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
                . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
                . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 "
                . "/Filter [/{$declaredFilter} /CCF] "
                . "/DecodeParms [null << /K -1 /Columns 16 /Rows 0 /EndOfBlock true >>] /Length {$declaredLength} >>\n"
                . "stream\n{$stream}\nendstream\nendobj\n"
                . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
                . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

            $review = $extractor->extractImageXObjectBoundaryReview($pdf);
            $entry = $review['entries'][0] ?? [];
            $boundary = $entry['ccitt_fax_filter_boundary'] ?? [];
            $plainText = $extractor->extractPlainText($pdf);

            $t->same(["Before {$declaredFilter} CCF import", "After {$declaredFilter} CCF import"], $extractor->extractTextLines($pdf));
            $t->same("Before {$declaredFilter} CCF import\nAfter {$declaredFilter} CCF import", $plainText);
            $t->true(!str_contains($plainText, 'Abbreviated CCITT native prefix leak'));
            $t->true(!str_contains($plainText, 'endstream'));
            $t->same([$declaredFilter, 'CCF'], $entry['filters'] ?? null);
            $t->same(['CCF'], $entry['preview_only_filters'] ?? null);
            $t->same([$declaredFilter], $boundary['native_prefix_filters'] ?? null);
            $t->same([$case['canonical']], $boundary['canonical_native_prefix_filters'] ?? null);
            $t->same(true, $entry['native_prefix_decoded'] ?? null);
            $t->same(strlen($nativeBytes), $entry['native_prefix_decoded_length'] ?? null);
            $t->same(hash('sha256', $nativeBytes), $entry['native_prefix_decoded_sha256'] ?? null);
            $t->same('CCF', $entry['stopped_before_filter'] ?? null);
            $t->same(false, $entry['decoded_with_current_filters'] ?? null);
            $t->same(false, $entry['native_raster_decode'] ?? null);
            $t->same(false, $entry['payload_in_visible_text'] ?? null);

            $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
            $t->true(!str_contains($encodedReview, 'Abbreviated CCITT native prefix leak'));
            $t->true(!str_contains($encodedReview, $nativeBytes));
        }
    },
];
