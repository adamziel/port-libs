<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$setUnsignedShort = static function (string $bytes, int $offset, int $value): string {
    return substr_replace($bytes, pack('n', $value & 0xFFFF), $offset, 2);
};

$setUnsignedLong = static function (string $bytes, int $offset, int $value): string {
    return substr_replace($bytes, pack('N', $value & 0xFFFFFFFF), $offset, 4);
};

$checksum = static function (string $bytes): int {
    $sum = 0;
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $word = str_pad(substr($bytes, $offset, 4), 4, "\0");
        $sum = ($sum + (unpack('N', $word)[1] ?? 0)) & 0xFFFFFFFF;
    }

    return $sum;
};

$simpleGlyph = static function (int $claimedYMax, int $actualY): string {
    return pack('nnnnn', 1, 0, 0, 100, $claimedYMax)
        . pack('n', 2)
        . pack('n', 0)
        . "\x01\x01\x01"
        . pack('nnn', 0, 100, (-100) & 0xFFFF)
        . pack('nnn', 0, 0, $actualY & 0xFFFF)
        . "\0";
};

$trueTypeFont = static function (
    int $actualSimpleY,
    bool $composite = false,
    int $compositeY = 0
) use (
    $setUnsignedShort,
    $setUnsignedLong,
    $checksum,
    $simpleGlyph
): string {
    $head = str_repeat("\0", 54);
    $head = $setUnsignedLong($head, 0, 0x00010000);
    $head = $setUnsignedLong($head, 4, 0x00010000);
    $head = $setUnsignedLong($head, 12, 0x5F0F3CF5);
    $head = $setUnsignedShort($head, 18, 1000);
    $head = $setUnsignedShort($head, 36, 0);
    $head = $setUnsignedShort($head, 38, 0);
    $head = $setUnsignedShort($head, 40, 100);
    $head = $setUnsignedShort($head, 42, 500);
    $head = $setUnsignedShort($head, 46, 8);
    $head = $setUnsignedShort($head, 48, 2);
    $head = $setUnsignedShort($head, 50, 0);

    $glyphCount = $composite ? 3 : 2;
    $maxp = str_repeat("\0", 32);
    $maxp = $setUnsignedLong($maxp, 0, 0x00010000);
    $maxp = $setUnsignedShort($maxp, 4, $glyphCount);

    $simple = $simpleGlyph(500, $actualSimpleY);
    $glyf = $simple;
    $locations = [0, 0, intdiv(strlen($simple), 2)];
    $mappedGlyph = 1;
    if ($composite) {
        // Explicit unscaled XY offsets and a one-half F2Dot14 scale exercise
        // the recursive composite path without legacy offset ambiguity.
        $compositeFlags = 0x100B;
        $compositeGlyph = pack('nnnnn', (-1) & 0xFFFF, 0, 0, 50, 250)
            . pack('nn', $compositeFlags, 1)
            . pack('nn', 0, $compositeY & 0xFFFF)
            . pack('n', 0x2000);
        $glyf .= $compositeGlyph;
        $locations[] = intdiv(strlen($glyf), 2);
        $mappedGlyph = 2;
    }
    $loca = '';
    foreach ($locations as $location) {
        $loca .= pack('n', $location);
    }

    $hhea = str_repeat("\0", 36);
    $hhea = $setUnsignedLong($hhea, 0, 0x00010000);
    $hhea = $setUnsignedShort($hhea, 4, 800);
    $hhea = $setUnsignedShort($hhea, 6, (-200) & 0xFFFF);
    $hhea = $setUnsignedShort($hhea, 10, 1000);
    $hhea = $setUnsignedShort($hhea, 18, 1);
    $hhea = $setUnsignedShort($hhea, 34, $glyphCount);
    $hmtx = pack('nnnn', 1000, 0, 100, 0);
    if ($composite) {
        $hmtx .= pack('nn', 50, 0);
    }

    $delta = ($mappedGlyph - 0x41) & 0xFFFF;
    $formatFour = pack('nnnnnnn', 4, 32, 0, 4, 4, 1, 0)
        . pack('nn', 0x41, 0xFFFF)
        . pack('n', 0)
        . pack('nn', 0x41, 0xFFFF)
        . pack('nn', $delta, 1)
        . pack('nn', 0, 0);
    $cmap = pack('nnnnN', 0, 1, 3, 1, 12) . $formatFour;

    $tables = [
        'cmap' => $cmap,
        'glyf' => $glyf,
        'head' => $head,
        'hhea' => $hhea,
        'hmtx' => $hmtx,
        'loca' => $loca,
        'maxp' => $maxp,
    ];
    ksort($tables, SORT_STRING);
    $tableCount = count($tables);
    $powerOfTwo = 1;
    $entrySelector = 0;
    while ($powerOfTwo * 2 <= $tableCount) {
        $powerOfTwo *= 2;
        $entrySelector++;
    }
    $directory = pack(
        'Nnnnn',
        0x00010000,
        $tableCount,
        $powerOfTwo * 16,
        $entrySelector,
        ($tableCount * 16) - ($powerOfTwo * 16)
    );
    $records = '';
    $payload = '';
    $tableOffset = 12 + ($tableCount * 16);
    $headOffset = null;
    foreach ($tables as $tag => $table) {
        $padding = (4 - (strlen($payload) % 4)) % 4;
        $payload .= str_repeat("\0", $padding);
        $tableOffset += $padding;
        if ($tag === 'head') {
            $headOffset = $tableOffset;
        }
        $records .= $tag . pack('NNN', $checksum($table), $tableOffset, strlen($table));
        $payload .= $table;
        $tableOffset += strlen($table);
    }
    $font = $directory . $records . $payload;
    if (!is_int($headOffset)) {
        throw new RuntimeException('Expected a head table offset.');
    }
    $adjustment = (0xB1B0AFBA - $checksum($font)) & 0xFFFFFFFF;
    return $setUnsignedLong($font, $headOffset + 8, $adjustment);
};

$pdfWithTrueType = static function (string $font): string {
    $content = 'BT /F1 10 Tf 1 0 0 1 100 100 Tm (A) Tj ET '
        . '0.5 w 99 108 m 102 108 l S';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
        . "/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /TrueType /BaseFont /OutlineBounds "
        . "/Encoding /WinAnsiEncoding /FirstChar 65 /LastChar 65 /Widths [100] "
        . "/FontDescriptor 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /FontDescriptor /FontName /OutlineBounds /Flags 32 "
        . "/FontBBox [0 0 100 500] /Ascent 500 /Descent 0 /CapHeight 500 "
        . "/StemV 80 /FontFile2 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($font) . " >>\nstream\n{$font}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'accepts a checksum-valid simple outline contained by its claimed boxes' => static function (
        TestRunner $t
    ) use ($trueTypeFont, $pdfWithTrueType, $checksum): void {
        $font = $trueTypeFont(500);
        $t->same('b1b0afba', sprintf('%08x', $checksum($font)));
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithTrueType($font)
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'rejects a checksum-valid glyph whose coordinates escape its claimed yMax' => static function (
        TestRunner $t
    ) use ($trueTypeFont, $pdfWithTrueType, $checksum): void {
        $font = $trueTypeFont(2000);
        $t->same('b1b0afba', sprintf('%08x', $checksum($font)));
        $extractor = new PdfTextExtractor();
        $pdf = $pdfWithTrueType($font);
        $visibility = $extractor->diagnostics($pdf)['textVisibility'];

        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(1, $visibility['unresolvedReasonCounts']['later-paint-occlusion'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },

    'accepts a bounded recursively transformed composite outline' => static function (
        TestRunner $t
    ) use ($trueTypeFont, $pdfWithTrueType): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithTrueType($trueTypeFont(500, true))
        )['textVisibility'];

        $t->same(0, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(true, $visibility['complete'] ?? null);
    },

    'rejects a composite translation that escapes its claimed box' => static function (
        TestRunner $t
    ) use ($trueTypeFont, $pdfWithTrueType): void {
        $visibility = (new PdfTextExtractor())->diagnostics(
            $pdfWithTrueType($trueTypeFont(500, true, 1000))
        )['textVisibility'];

        $t->same(1, $visibility['unresolvedOcclusionRiskRuns'] ?? null);
        $t->same(false, $visibility['complete'] ?? null);
    },
];
