<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UnicodeText
{
    private const REPLACEMENT = "\xEF\xBF\xBD";

    /** @var array<int, int> */
    private const WINDOWS_1252_CONTROLS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8a => 0x0160,
        0x8b => 0x2039,
        0x8c => 0x0152,
        0x8e => 0x017d,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02dc,
        0x99 => 0x2122,
        0x9a => 0x0161,
        0x9b => 0x203a,
        0x9c => 0x0153,
        0x9e => 0x017e,
        0x9f => 0x0178,
    ];

    /**
     * @return array{text:string, encoding:string, bom:string|null, repairs:int}
     */
    public static function decodeBytes(string $bytes, ?string $encoding = null): array
    {
        $normalized = self::normalizeEncoding($encoding);
        $bom = null;
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bom = 'utf-8';
            $bytes = substr($bytes, 3);
            $normalized ??= 'utf-8';
        } elseif (str_starts_with($bytes, "\xFF\xFE")) {
            $bom = 'utf-16le';
            $bytes = substr($bytes, 2);
            $normalized ??= 'utf-16le';
        } elseif (str_starts_with($bytes, "\xFE\xFF")) {
            $bom = 'utf-16be';
            $bytes = substr($bytes, 2);
            $normalized ??= 'utf-16be';
        }

        $normalized ??= 'utf-8';
        if ($normalized === 'utf-16') {
            $normalized = $bom === 'utf-16be' ? 'utf-16be' : 'utf-16le';
        }

        if ($normalized === 'utf-16le' || $normalized === 'utf-16be') {
            [$text, $repairs] = self::decodeUtf16($bytes, $normalized === 'utf-16le');

            return [
                'text' => $text,
                'encoding' => $normalized,
                'bom' => $bom,
                'repairs' => $repairs,
            ];
        }

        if ($normalized === 'windows-1252' || $normalized === 'iso-8859-1') {
            [$text, $repairs] = self::decodeSingleByte($bytes, $normalized === 'windows-1252');

            return [
                'text' => $text,
                'encoding' => $normalized,
                'bom' => $bom,
                'repairs' => $repairs,
            ];
        }

        [$text, $repairs] = self::repairUtf8($bytes);

        return [
            'text' => $text,
            'encoding' => $repairs === 0 ? 'utf-8' : 'utf-8-repaired',
            'bom' => $bom,
            'repairs' => $repairs,
        ];
    }

    public static function repair(string $text): string
    {
        return self::repairUtf8($text)[0];
    }

    /**
     * @return list<string>
     */
    public static function characters(string $text): array
    {
        $text = self::repair($text);
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $chars === false ? str_split($text) : $chars;
    }

    /**
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        $clusters = [];
        $joinNext = false;
        foreach (self::characters($text) as $char) {
            $codepoint = self::codepoint($char);
            if (
                $clusters === []
                || (!$joinNext && !self::isCombiningOrZeroWidth($codepoint))
            ) {
                $clusters[] = $char;
            } else {
                $clusters[count($clusters) - 1] .= $char;
            }
            $joinNext = $codepoint === 0x200d;
        }

        return $clusters;
    }

    public static function displayWidth(string $text): int
    {
        $width = 0;
        foreach (self::graphemes($text) as $cluster) {
            $width += self::graphemeDisplayWidth($cluster);
        }

        return $width;
    }

    public static function padDisplay(string $text, int $width, string $alignment = 'left'): string
    {
        $padding = max(0, $width - self::displayWidth($text));

        return match ($alignment) {
            'right' => str_repeat(' ', $padding) . $text,
            'center' => str_repeat(' ', intdiv($padding, 2)) . $text . str_repeat(' ', $padding - intdiv($padding, 2)),
            default => $text . str_repeat(' ', $padding),
        };
    }

    private static function normalizeEncoding(?string $encoding): ?string
    {
        if ($encoding === null || trim($encoding) === '') {
            return null;
        }

        $key = strtolower(str_replace(['-', '_', ' '], '', trim($encoding)));

        return match ($key) {
            'utf8' => 'utf-8',
            'utf16' => 'utf-16',
            'utf16le' => 'utf-16le',
            'utf16be' => 'utf-16be',
            'windows1252', 'cp1252', 'msansi' => 'windows-1252',
            'iso88591', 'latin1', 'latin-1' => 'iso-8859-1',
            default => 'utf-8',
        };
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function repairUtf8(string $bytes): array
    {
        if ($bytes === '' || preg_match('//u', $bytes) === 1) {
            return [$bytes, 0];
        }

        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length;) {
            $first = ord($bytes[$offset]);
            if ($first <= 0x7f) {
                $out .= $bytes[$offset];
                $offset++;
                continue;
            }

            $sequenceLength = match (true) {
                $first >= 0xc2 && $first <= 0xdf => 2,
                $first >= 0xe0 && $first <= 0xef => 3,
                $first >= 0xf0 && $first <= 0xf4 => 4,
                default => 0,
            };

            if ($sequenceLength === 0 || $offset + $sequenceLength > $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $bytesAt = [];
            for ($index = 0; $index < $sequenceLength; $index++) {
                $bytesAt[] = ord($bytes[$offset + $index]);
            }

            if (!self::isValidUtf8Sequence($bytesAt)) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= substr($bytes, $offset, $sequenceLength);
            $offset += $sequenceLength;
        }

        return [$out, $repairs];
    }

    /**
     * @param list<int> $bytes
     */
    private static function isValidUtf8Sequence(array $bytes): bool
    {
        foreach (array_slice($bytes, 1) as $byte) {
            if ($byte < 0x80 || $byte > 0xbf) {
                return false;
            }
        }

        $first = $bytes[0];
        if (count($bytes) === 3) {
            $second = $bytes[1];
            if ($first === 0xe0 && $second < 0xa0) {
                return false;
            }
            if ($first === 0xed && $second > 0x9f) {
                return false;
            }
        }
        if (count($bytes) === 4) {
            $second = $bytes[1];
            if ($first === 0xf0 && $second < 0x90) {
                return false;
            }
            if ($first === 0xf4 && $second > 0x8f) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeUtf16(string $bytes, bool $littleEndian): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            if ($offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                break;
            }

            $unit = self::u16($bytes, $offset, $littleEndian);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 3 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $trail = self::u16($bytes, $offset + 2, $littleEndian);
                if ($trail < 0xdc00 || $trail > 0xdfff) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($trail - 0xdc00));
                $offset += 2;
                continue;
            }

            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $out .= self::fromCodepoint($unit);
        }

        return [$out, $repairs];
    }

    private static function u16(string $bytes, int $offset, bool $littleEndian): int
    {
        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);

        return $littleEndian ? ($first | ($second << 8)) : (($first << 8) | $second);
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeSingleByte(string $bytes, bool $windows1252): array
    {
        $out = '';
        $repairs = 0;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($windows1252 && $byte >= 0x80 && $byte <= 0x9f) {
                if (isset(self::WINDOWS_1252_CONTROLS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1252_CONTROLS[$byte]);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            $out .= self::fromCodepoint($byte);
        }

        return [$out, $repairs];
    }

    private static function fromCodepoint(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return self::REPLACEMENT;
        }

        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function codepoint(string $char): int
    {
        $bytes = array_values(unpack('C*', $char) ?: []);
        $first = $bytes[0] ?? 0;
        if ($first <= 0x7f) {
            return $first;
        }
        if ($first >= 0xc2 && $first <= 0xdf && isset($bytes[1])) {
            return (($first & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($first >= 0xe0 && $first <= 0xef && isset($bytes[2])) {
            return (($first & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($first >= 0xf0 && $first <= 0xf4 && isset($bytes[3])) {
            return (($first & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        return 0xfffd;
    }

    private static function codepointDisplayWidth(int $codepoint): int
    {
        if ($codepoint === 0 || $codepoint < 32 || ($codepoint >= 0x7f && $codepoint < 0xa0)) {
            return 0;
        }
        if (self::isCombiningOrZeroWidth($codepoint)) {
            return 0;
        }
        if (self::isWideCodepoint($codepoint)) {
            return 2;
        }

        return 1;
    }

    private static function graphemeDisplayWidth(string $cluster): int
    {
        $width = 0;
        $hasJoiner = false;
        $hasWide = false;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            $charWidth = self::codepointDisplayWidth($codepoint);
            $width += $charWidth;
            $hasJoiner = $hasJoiner || $codepoint === 0x200d;
            $hasWide = $hasWide || $charWidth === 2;
        }

        return $hasJoiner && $hasWide ? 2 : $width;
    }

    private static function isCombiningOrZeroWidth(int $codepoint): bool
    {
        return ($codepoint >= 0x0300 && $codepoint <= 0x036f)
            || ($codepoint >= 0x0483 && $codepoint <= 0x0489)
            || ($codepoint >= 0x0591 && $codepoint <= 0x05bd)
            || $codepoint === 0x05bf
            || ($codepoint >= 0x05c1 && $codepoint <= 0x05c2)
            || ($codepoint >= 0x05c4 && $codepoint <= 0x05c5)
            || $codepoint === 0x05c7
            || ($codepoint >= 0x0610 && $codepoint <= 0x061a)
            || ($codepoint >= 0x064b && $codepoint <= 0x065f)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06d6 && $codepoint <= 0x06dc)
            || ($codepoint >= 0x06df && $codepoint <= 0x06e4)
            || ($codepoint >= 0x06e7 && $codepoint <= 0x06e8)
            || ($codepoint >= 0x06ea && $codepoint <= 0x06ed)
            || ($codepoint >= 0x0711 && $codepoint <= 0x0711)
            || ($codepoint >= 0x0730 && $codepoint <= 0x074a)
            || ($codepoint >= 0x07a6 && $codepoint <= 0x07b0)
            || ($codepoint >= 0x07eb && $codepoint <= 0x07f3)
            || ($codepoint >= 0x0816 && $codepoint <= 0x0819)
            || ($codepoint >= 0x081b && $codepoint <= 0x0823)
            || ($codepoint >= 0x0825 && $codepoint <= 0x0827)
            || ($codepoint >= 0x0829 && $codepoint <= 0x082d)
            || ($codepoint >= 0x0859 && $codepoint <= 0x085b)
            || ($codepoint >= 0x08d3 && $codepoint <= 0x08ff)
            || ($codepoint >= 0x0900 && $codepoint <= 0x0902)
            || $codepoint === 0x093a
            || $codepoint === 0x093c
            || ($codepoint >= 0x0941 && $codepoint <= 0x0948)
            || $codepoint === 0x094d
            || ($codepoint >= 0x0951 && $codepoint <= 0x0957)
            || ($codepoint >= 0x0962 && $codepoint <= 0x0963)
            || ($codepoint >= 0x0981 && $codepoint <= 0x0981)
            || $codepoint === 0x09bc
            || ($codepoint >= 0x09c1 && $codepoint <= 0x09c4)
            || $codepoint === 0x09cd
            || ($codepoint >= 0x09e2 && $codepoint <= 0x09e3)
            || ($codepoint >= 0x0e31 && $codepoint <= 0x0e31)
            || ($codepoint >= 0x0e34 && $codepoint <= 0x0e3a)
            || ($codepoint >= 0x0e47 && $codepoint <= 0x0e4e)
            || ($codepoint >= 0x200b && $codepoint <= 0x200f)
            || ($codepoint >= 0x202a && $codepoint <= 0x202e)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206f)
            || ($codepoint >= 0x20d0 && $codepoint <= 0x20ff)
            || ($codepoint >= 0xfe00 && $codepoint <= 0xfe0f)
            || ($codepoint >= 0xfe20 && $codepoint <= 0xfe2f)
            || ($codepoint >= 0xe0100 && $codepoint <= 0xe01ef);
    }

    private static function isWideCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x1100 && $codepoint <= 0x115f)
            || $codepoint === 0x2329
            || $codepoint === 0x232a
            || ($codepoint >= 0x2e80 && $codepoint <= 0xa4cf && $codepoint !== 0x303f)
            || ($codepoint >= 0xac00 && $codepoint <= 0xd7a3)
            || ($codepoint >= 0xf900 && $codepoint <= 0xfaff)
            || ($codepoint >= 0xfe10 && $codepoint <= 0xfe19)
            || ($codepoint >= 0xfe30 && $codepoint <= 0xfe6f)
            || ($codepoint >= 0xff00 && $codepoint <= 0xff60)
            || ($codepoint >= 0xffe0 && $codepoint <= 0xffe6)
            || ($codepoint >= 0x1f300 && $codepoint <= 0x1f64f)
            || ($codepoint >= 0x1f900 && $codepoint <= 0x1f9ff)
            || ($codepoint >= 0x20000 && $codepoint <= 0x3fffd);
    }
}
