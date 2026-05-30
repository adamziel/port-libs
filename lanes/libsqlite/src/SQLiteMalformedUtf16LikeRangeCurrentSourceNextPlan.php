<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{
     *   pattern:string,
     *   collation:string,
     *   range:?array{lowerInclusive:string,upperBound:?string},
     *   currentSource:string,
     *   nextSource:string,
     *   sourceChanged:bool,
     *   cursorInvalidated:bool,
     *   invalidationReasons:list<string>,
     *   currentRowids:list<int>,
     *   nextRowids:list<int>,
     *   malformedCurrentRowids:list<int>,
     *   malformedNextRowids:list<int>,
     *   omittedMalformedCurrentRowids:list<int>,
     *   omittedMalformedNextRowids:list<int>,
     *   retainedRowids:list<int>,
     *   exitedRowids:list<int>,
     *   enteredRowids:list<int>,
     *   currentDiagnostics:array<int,array{encoding:string,bytesHex:string,decoded:?string,malformed:bool,malformedReason:?string,inRange:bool,residualMatch:bool,omitted:bool}>,
     *   nextDiagnostics:array<int,array{encoding:string,bytesHex:string,decoded:?string,malformed:bool,malformedReason:?string,inRange:bool,residualMatch:bool,omitted:bool}>,
     *   dependencies:list<string>
     * }
     */
    public static function keyValueRowKeyLikeRange(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $collation = 'NOCASE',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'current',
        string $nextSource = 'next',
    ): array {
        $collation = self::normalizeCollation($collation);
        $range = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike)['range'];
        $current = self::scan($currentRows, $pattern, $collation, $escape, $caseSensitiveLike, $range);
        $next = self::scan($nextRows, $pattern, $collation, $escape, $caseSensitiveLike, $range);

        $currentRowids = self::matchedRowids($current);
        $nextRowids = self::matchedRowids($next);
        $malformedCurrent = self::malformedRowids($current);
        $malformedNext = self::malformedRowids($next);
        $omittedCurrent = self::omittedMalformedRowids($current);
        $omittedNext = self::omittedMalformedRowids($next);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($malformedCurrent !== [] || $malformedNext !== []) {
            $reasons[] = 'malformed-utf16';
        }
        if ($omittedCurrent !== [] || $omittedNext !== []) {
            $reasons[] = 'omitted-malformed-range-row';
        }
        if ($currentRowids !== $nextRowids) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'pattern' => $pattern,
            'collation' => $collation,
            'range' => $range,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'sourceChanged' => $currentSource !== $nextSource,
            'cursorInvalidated' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'malformedCurrentRowids' => $malformedCurrent,
            'malformedNextRowids' => $malformedNext,
            'omittedMalformedCurrentRowids' => $omittedCurrent,
            'omittedMalformedNextRowids' => $omittedNext,
            'retainedRowids' => array_values(array_intersect($currentRowids, $nextRowids)),
            'exitedRowids' => array_values(array_diff($currentRowids, $nextRowids)),
            'enteredRowids' => array_values(array_diff($nextRowids, $currentRowids)),
            'currentDiagnostics' => self::diagnosticsByRowid($current),
            'nextDiagnostics' => self::diagnosticsByRowid($next),
            'dependencies' => [
                'sqlite-like-range-bounds',
                'sqlite-tolerant-utf16-source-decode',
                'sqlite-current-source-next-invalidation',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array{rowid:int,encoding:string,bytes:string,decoded:?string,malformed:bool,malformedReason:?string,inRange:bool,residualMatch:bool,matched:bool,omitted:bool}>
     */
    private static function scan(array $rows, string $pattern, string $collation, ?string $escape, bool $caseSensitiveLike, ?array $range): array
    {
        $scanned = [];
        foreach ($rows as $row) {
            $rowid = $row['option_id'] ?? null;
            $bytes = $row['option_name_bytes'] ?? null;
            $encoding = $row['text_encoding'] ?? null;
            if (!is_int($rowid)) {
                throw new \InvalidArgumentException('SQLite malformed UTF-16 LIKE range rows require integer option_id');
            }
            if (!is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite malformed UTF-16 LIKE range rows require option_name_bytes');
            }
            if (!is_int($encoding) || !in_array($encoding, [2, 3], true)) {
                throw new \InvalidArgumentException('SQLite malformed UTF-16 LIKE range rows require UTF-16LE or UTF-16BE text_encoding');
            }

            $decoded = self::decodeUtf16Tolerant($bytes, $encoding);
            $text = $decoded['text'];
            $inRange = $text !== null && self::inRange($text, $range, $collation);
            $residual = $text !== null && SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike);
            $scanned[] = [
                'rowid' => $rowid,
                'encoding' => $encoding === 2 ? 'UTF-16LE' : 'UTF-16BE',
                'bytes' => $bytes,
                'decoded' => $text,
                'malformed' => $decoded['malformed'],
                'malformedReason' => $decoded['reason'],
                'inRange' => $inRange,
                'residualMatch' => $residual,
                'matched' => !$decoded['malformed'] && $inRange && $residual,
                'omitted' => $decoded['malformed'],
            ];
        }

        usort($scanned, static function (array $left, array $right) use ($collation): int {
            if ($left['decoded'] === null && $right['decoded'] === null) {
                return $left['rowid'] <=> $right['rowid'];
            }
            if ($left['decoded'] === null) {
                return 1;
            }
            if ($right['decoded'] === null) {
                return -1;
            }
            $comparison = self::compareText($left['decoded'], $right['decoded'], $collation);
            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $scanned;
    }

    /**
     * @param list<array{rowid:int,matched:bool}> $rows
     * @return list<int>
     */
    private static function matchedRowids(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): int => $row['rowid'],
            array_values(array_filter($rows, static fn (array $row): bool => $row['matched']))
        ));
    }

    /**
     * @param list<array{rowid:int,malformed:bool}> $rows
     * @return list<int>
     */
    private static function malformedRowids(array $rows): array
    {
        $rowids = array_values(array_map(
            static fn (array $row): int => $row['rowid'],
            array_values(array_filter($rows, static fn (array $row): bool => $row['malformed']))
        ));
        sort($rowids);

        return $rowids;
    }

    /**
     * @param list<array{rowid:int,omitted:bool}> $rows
     * @return list<int>
     */
    private static function omittedMalformedRowids(array $rows): array
    {
        $rowids = array_values(array_map(
            static fn (array $row): int => $row['rowid'],
            array_values(array_filter($rows, static fn (array $row): bool => $row['omitted']))
        ));
        sort($rowids);

        return $rowids;
    }

    /**
     * @param list<array{rowid:int,encoding:string,bytes:string,decoded:?string,malformed:bool,malformedReason:?string,inRange:bool,residualMatch:bool,omitted:bool}> $rows
     * @return array<int,array{encoding:string,bytesHex:string,decoded:?string,malformed:bool,malformedReason:?string,inRange:bool,residualMatch:bool,omitted:bool}>
     */
    private static function diagnosticsByRowid(array $rows): array
    {
        $diagnostics = [];
        foreach ($rows as $row) {
            $diagnostics[$row['rowid']] = [
                'encoding' => $row['encoding'],
                'bytesHex' => bin2hex($row['bytes']),
                'decoded' => $row['decoded'],
                'malformed' => $row['malformed'],
                'malformedReason' => $row['malformedReason'],
                'inRange' => $row['inRange'],
                'residualMatch' => $row['residualMatch'],
                'omitted' => $row['omitted'],
            ];
        }
        ksort($diagnostics);

        return $diagnostics;
    }

    /**
     * @return array{text:?string,malformed:bool,reason:?string}
     */
    private static function decodeUtf16Tolerant(string $bytes, int $encoding): array
    {
        if (strlen($bytes) % 2 !== 0) {
            return ['text' => null, 'malformed' => true, 'reason' => 'odd-byte-length'];
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::unpackUint16(substr($bytes, $offset, 2), $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    return ['text' => null, 'malformed' => true, 'reason' => 'trailing-high-surrogate'];
                }
                $low = self::unpackUint16(substr($bytes, $offset + 2, 2), $encoding);
                if ($low < 0xdc00 || $low > 0xdfff) {
                    return ['text' => null, 'malformed' => true, 'reason' => 'unpaired-high-surrogate'];
                }
                $text .= self::utf8FromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($low - 0xdc00));
                $offset += 2;
                continue;
            }
            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                return ['text' => null, 'malformed' => true, 'reason' => 'unpaired-low-surrogate'];
            }
            $text .= self::utf8FromCodepoint($unit);
        }

        return ['text' => $text, 'malformed' => false, 'reason' => null];
    }

    /**
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function inRange(string $text, ?array $range, string $collation): bool
    {
        if ($range === null) {
            return false;
        }
        if (self::compareText($text, $range['lowerInclusive'], $collation) < 0) {
            return false;
        }

        return $range['upperBound'] === null || self::compareText($text, $range['upperBound'], $collation) < 0;
    }

    private static function compareText(string $left, string $right, string $collation): int
    {
        return match ($collation) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
        };
    }

    private static function normalizeCollation(string $collation): string
    {
        $normalized = strtoupper($collation);
        if (!in_array($normalized, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite malformed UTF-16 LIKE range collation: {$collation}");
        }

        return $normalized;
    }

    private static function unpackUint16(string $bytes, int $encoding): int
    {
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);

        return $encoding === 2
            ? $first | ($second << 8)
            : ($first << 8) | $second;
    }

    private static function utf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3f)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
