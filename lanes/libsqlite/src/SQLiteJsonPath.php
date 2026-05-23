<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPath
{
    public static function decodeBareMember(string $member): ?string
    {
        $decoded = '';
        $length = strlen($member);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $member[$offset];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }
            if ($offset + 1 >= $length) {
                $decoded .= '\\';
                continue;
            }

            $escape = $member[++$offset];
            switch ($escape) {
                case '"':
                case "'":
                case '\\':
                case '/':
                    $decoded .= $escape;
                    break;
                case 'b':
                    $decoded .= "\x08";
                    break;
                case 'f':
                    $decoded .= "\x0c";
                    break;
                case 'n':
                    $decoded .= "\n";
                    break;
                case 'r':
                    $decoded .= "\r";
                    break;
                case 't':
                    $decoded .= "\t";
                    break;
                case 'v':
                    $decoded .= "\x0b";
                    break;
                case '0':
                    $decoded .= "\0";
                    break;
                case 'x':
                    if ($offset + 2 >= $length) {
                        return null;
                    }
                    $hex = substr($member, $offset + 1, 2);
                    if (preg_match('/^[0-9A-Fa-f]{2}$/', $hex) !== 1) {
                        return null;
                    }
                    $decoded .= chr(hexdec($hex));
                    $offset += 2;
                    break;
                case 'u':
                    $utf8 = self::decodeUnicodeEscape($member, $offset);
                    if ($utf8 === null) {
                        return null;
                    }
                    $decoded .= $utf8['text'];
                    $offset = $utf8['offset'];
                    break;
                default:
                    $decoded .= $escape;
                    break;
            }
        }

        return $decoded;
    }

    /**
     * @return null|array{text:string,offset:int}
     */
    private static function decodeUnicodeEscape(string $member, int $offset): ?array
    {
        $length = strlen($member);
        if ($offset + 4 >= $length) {
            return null;
        }

        $hex = substr($member, $offset + 1, 4);
        if (preg_match('/^[0-9A-Fa-f]{4}$/', $hex) !== 1) {
            return null;
        }

        $codepoint = hexdec($hex);
        $offset += 4;
        if (
            $codepoint >= 0xd800
            && $codepoint <= 0xdbff
            && $offset + 6 < $length
            && substr($member, $offset + 1, 2) === '\\u'
            && preg_match('/^[0-9A-Fa-f]{4}$/', substr($member, $offset + 3, 4)) === 1
        ) {
            $low = hexdec(substr($member, $offset + 3, 4));
            if ($low >= 0xdc00 && $low <= 0xdfff) {
                $codepoint = 0x10000 + (($codepoint - 0xd800) << 10) + ($low - 0xdc00);
                $offset += 6;
            }
        }

        $text = self::codepointToUtf8($codepoint);
        if ($text === null) {
            return null;
        }

        return [
            'text' => $text,
            'offset' => $offset,
        ];
    }

    private static function codepointToUtf8(int $codepoint): ?string
    {
        if ($codepoint < 0 || ($codepoint >= 0xd800 && $codepoint <= 0xdfff) || $codepoint > 0x10ffff) {
            return null;
        }
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }
}
