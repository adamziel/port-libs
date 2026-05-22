<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class FileContentDecoder
{
    public function guessTextContent(string $bytes): ?string
    {
        if ($this->isValidUtf8($bytes)) {
            return $bytes;
        }

        if (!$this->hasUtf16ByteOrderMark($bytes)) {
            return null;
        }

        return $this->decodeUtf16WithByteOrderMark($bytes);
    }

    public function isText(string $bytes): bool
    {
        return $this->guessTextContent($bytes) !== null;
    }

    private function isValidUtf8(string $bytes): bool
    {
        return preg_match('//u', $bytes) === 1;
    }

    private function hasUtf16ByteOrderMark(string $bytes): bool
    {
        return str_starts_with($bytes, "\xfe\xff") || str_starts_with($bytes, "\xff\xfe");
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
