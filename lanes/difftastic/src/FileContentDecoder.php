<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

use InvalidArgumentException;

final class FileContentDecoder
{
    private const WINDOWS_1252_C1_MAP = [
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
     * @param list<string> $binaryOverrideGlobs
     */
    public function guessTextContent(string $bytes, ?string $path = null, array $binaryOverrideGlobs = []): ?string
    {
        if ($path !== null && $this->matchesBinaryOverride($path, $binaryOverrideGlobs)) {
            return null;
        }

        if ($this->hasCommonBinarySignature($bytes)) {
            return null;
        }

        if ($this->isValidUtf8($bytes)) {
            return $bytes;
        }

        if ($this->hasUtf16ByteOrderMark($bytes)) {
            return $this->decodeUtf16WithByteOrderMark($bytes);
        }

        $mostlyUtf8 = $this->decodeMostlyValidUtf8($bytes);
        if ($mostlyUtf8 !== null) {
            return $mostlyUtf8;
        }

        return $this->decodeWindows1252Text($bytes);
    }

    /**
     * @param list<string> $binaryOverrideGlobs
     */
    public function isText(string $bytes, ?string $path = null, array $binaryOverrideGlobs = []): bool
    {
        return $this->guessTextContent($bytes, $path, $binaryOverrideGlobs) !== null;
    }

    /**
     * @param list<string> $binaryOverrideGlobs
     */
    public function matchesBinaryOverride(string $path, array $binaryOverrideGlobs): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);
        foreach ($binaryOverrideGlobs as $glob) {
            $normalizedGlob = str_replace('\\', '/', $glob);
            if (!$this->isValidGlob($normalizedGlob)) {
                throw new InvalidArgumentException("Invalid glob syntax '{$glob}'");
            }
            if (fnmatch($normalizedGlob, $normalizedPath)) {
                return true;
            }
        }

        return false;
    }

    private function isValidUtf8(string $bytes): bool
    {
        return preg_match('//u', $bytes) === 1;
    }

    private function hasUtf16ByteOrderMark(string $bytes): bool
    {
        return str_starts_with($bytes, "\xfe\xff") || str_starts_with($bytes, "\xff\xfe");
    }

    private function hasCommonBinarySignature(string $bytes): bool
    {
        foreach ([
            "\x89PNG\r\n\x1a\n",
            "\x1f\x8b",
            "\xff\xd8\xff",
            "PK\x03\x04",
            "PK\x05\x06",
            "PK\x07\x08",
            "dex\n035\0",
            "dex\n036\0",
            "dex\n037\0",
            "dex\n038\0",
            "dex\n039\0",
        ] as $signature) {
            if (str_starts_with($bytes, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function decodeMostlyValidUtf8(string $bytes): ?string
    {
        $text = '';
        $invalidOrNulls = 0;
        $characters = 0;
        $length = strlen($bytes);

        for ($offset = 0; $offset < $length;) {
            $byte = ord($bytes[$offset]);
            if ($byte < 0x80) {
                $text .= $bytes[$offset];
                if ($byte === 0x00 && $characters < 50_000) {
                    $invalidOrNulls++;
                }
                $offset++;
                $characters++;
                continue;
            }

            $sequenceLength = $this->utf8SequenceLength($byte);
            if ($sequenceLength !== null && $offset + $sequenceLength <= $length) {
                $sequence = substr($bytes, $offset, $sequenceLength);
                if ($this->isValidUtf8($sequence)) {
                    $text .= $sequence;
                    $offset += $sequenceLength;
                    $characters++;
                    continue;
                }
            }

            $text .= "\xef\xbf\xbd";
            if ($characters < 50_000) {
                $invalidOrNulls++;
                if ($invalidOrNulls > 2) {
                    return null;
                }
            }
            $offset++;
            $characters++;
        }

        return $invalidOrNulls <= 2 && $this->isValidUtf8($text) ? $text : null;
    }

    private function utf8SequenceLength(int $byte): ?int
    {
        if ($byte >= 0xc2 && $byte <= 0xdf) {
            return 2;
        }
        if ($byte >= 0xe0 && $byte <= 0xef) {
            return 3;
        }
        if ($byte >= 0xf0 && $byte <= 0xf4) {
            return 4;
        }

        return null;
    }

    private function isValidGlob(string $glob): bool
    {
        return substr_count($glob, '[') === substr_count($glob, ']');
    }

    private function decodeWindows1252Text(string $bytes): ?string
    {
        if (!$this->looksLikeWindows1252Text($bytes)) {
            return null;
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte < 0x80) {
                $codePoint = $byte;
            } elseif ($byte < 0xa0) {
                if (!isset(self::WINDOWS_1252_C1_MAP[$byte])) {
                    return null;
                }

                $codePoint = self::WINDOWS_1252_C1_MAP[$byte];
            } else {
                $codePoint = $byte;
            }

            $text .= $this->utf8CodePoint($codePoint);
        }

        return $this->isValidUtf8($text) ? $text : null;
    }

    private function looksLikeWindows1252Text(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        $hasHighByte = false;
        $nulls = 0;
        $length = min(strlen($bytes), 50_000);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte >= 0x80) {
                $hasHighByte = true;
            }

            if ($byte === 0x00) {
                $nulls++;
                if ($nulls > 1) {
                    return false;
                }

                continue;
            }

            if ($byte < 0x20 && !in_array($byte, [0x09, 0x0a, 0x0d], true)) {
                return false;
            }

            if ($byte >= 0x80 && $byte < 0xa0 && !isset(self::WINDOWS_1252_C1_MAP[$byte])) {
                return false;
            }
        }

        return $hasHighByte;
    }

    private function decodeUtf16WithByteOrderMark(string $bytes): ?string
    {
        $bigEndian = str_starts_with($bytes, "\xfe\xff");
        $length = strlen($bytes) - (strlen($bytes) % 2);
        $text = '';

        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = $bigEndian
                ? (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1])
                : ord($bytes[$offset]) | (ord($bytes[$offset + 1]) << 8);

            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 3 >= $length) {
                    return null;
                }
                $next = $bigEndian
                    ? (ord($bytes[$offset + 2]) << 8) | ord($bytes[$offset + 3])
                    : ord($bytes[$offset + 2]) | (ord($bytes[$offset + 3]) << 8);
                if ($next < 0xdc00 || $next > 0xdfff) {
                    return null;
                }

                $codePoint = 0x10000 + (($unit - 0xd800) << 10) + ($next - 0xdc00);
                $offset += 2;
            } elseif ($unit >= 0xdc00 && $unit <= 0xdfff) {
                return null;
            } else {
                $codePoint = $unit;
            }

            $text .= $this->utf8CodePoint($codePoint);
        }

        return $this->isValidUtf8($text) ? $text : null;
    }

    private function utf8CodePoint(int $codePoint): string
    {
        if ($codePoint <= 0x7f) {
            return chr($codePoint);
        }
        if ($codePoint <= 0x7ff) {
            return chr(0xc0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3f));
        }
        if ($codePoint <= 0xffff) {
            return chr(0xe0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3f))
                . chr(0x80 | ($codePoint & 0x3f));
        }

        return chr(0xf0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3f))
            . chr(0x80 | (($codePoint >> 6) & 0x3f))
            . chr(0x80 | ($codePoint & 0x3f));
    }
}
