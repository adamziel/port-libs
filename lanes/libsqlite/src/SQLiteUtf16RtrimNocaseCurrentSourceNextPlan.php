<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16RtrimNocaseCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCurrentNext(
        array $currentRows,
        array $nextRows,
        string $probe,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
    ): array {
        $current = self::sourceRows($currentRows, $probe);
        $next = self::sourceRows($nextRows, $probe);
        $currentRowids = self::rowids($current['matches']);
        $nextRowids = self::rowids($next['matches']);
        $changes = self::matchedSourceChanges($current['matches'], $next['matches']);
        $reprepareReasons = self::reprepareReasons(
            $currentSource,
            $nextSource,
            $currentRowids,
            $nextRowids,
            $current['errors'],
            $next['errors'],
            $changes['encodingChangedRowids'],
            $changes['bytesChangedRowids'],
            $changes['comparisonKeyChangedRowids'],
        );

        return [
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'probe' => $probe,
            'probeKey' => self::rtrimNocaseKey($probe),
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => array_values(array_intersect($currentRowids, $nextRowids)),
            'enteredRowids' => array_values(array_diff($nextRowids, $currentRowids)),
            'exitedRowids' => array_values(array_diff($currentRowids, $nextRowids)),
            'currentOrderRowids' => self::rowids($current['valid']),
            'nextOrderRowids' => self::rowids($next['valid']),
            'currentComparisonKeys' => self::comparisonKeys($current['valid']),
            'nextComparisonKeys' => self::comparisonKeys($next['valid']),
            'currentBytesHex' => self::bytesHex($current['valid']),
            'nextBytesHex' => self::bytesHex($next['valid']),
            'currentEncodings' => self::encodings($current['valid']),
            'nextEncodings' => self::encodings($next['valid']),
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'retainedComparisonKeyChangedRowids' => $changes['comparisonKeyChangedRowids'],
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'repairedRowids' => array_values(array_diff(array_keys($current['errors']), array_keys($next['errors']))),
            'newlyMalformedRowids' => array_values(array_diff(array_keys($next['errors']), array_keys($current['errors']))),
            'sourceChanged' => $currentSource !== $nextSource,
            'reprepareRequired' => $reprepareReasons !== [],
            'reprepareReasons' => $reprepareReasons,
            'dependencies' => ['sqlite-utf16-decode', 'sqlite-rtrim-expression', 'sqlite-nocase-collation', 'sqlite-current-source-byte-invalidation'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{valid:list<array{rowid:int,text:string,key:string,bytes:string,encoding:string,payload:array<string,mixed>}>,matches:list<array{rowid:int,text:string,key:string,bytes:string,encoding:string,payload:array<string,mixed>}>,errors:array<int,string>}
     */
    private static function sourceRows(array $rows, string $probe): array
    {
        $valid = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE current/next rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE current/next rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE current/next rows require integer text_encoding');
            }

            try {
                $encoding = self::encodingName($row['text_encoding']);
                $text = self::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $valid[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'key' => self::rtrimNocaseKey($text),
                    'bytes' => $row['option_name_bytes'],
                    'encoding' => $encoding,
                    'payload' => $row,
                ];
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($valid, static fn (array $left, array $right): int => $left['key'] === $right['key']
            ? $left['rowid'] <=> $right['rowid']
            : strcmp($left['key'], $right['key']));

        $probeKey = self::rtrimNocaseKey($probe);
        $matches = array_values(array_filter(
            $valid,
            static fn (array $row): bool => $row['key'] === $probeKey,
        ));

        return ['valid' => $valid, 'matches' => $matches, 'errors' => $errors];
    }

    private static function rtrimNocaseKey(string $text): string
    {
        return self::asciiLower(rtrim($text, ' '));
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /**
     * @param list<array{rowid:int,key:string}> $rows
     * @return array<int,string>
     */
    private static function comparisonKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['rowid']] = $row['key'];
        }

        return $keys;
    }

    /**
     * @param list<array{rowid:int,bytes:string}> $rows
     * @return array<int,string>
     */
    private static function bytesHex(array $rows): array
    {
        $bytes = [];
        foreach ($rows as $row) {
            $bytes[$row['rowid']] = bin2hex($row['bytes']);
        }

        return $bytes;
    }

    /**
     * @param list<array{rowid:int,encoding:string}> $rows
     * @return array<int,string>
     */
    private static function encodings(array $rows): array
    {
        $encodings = [];
        foreach ($rows as $row) {
            $encodings[$row['rowid']] = $row['encoding'];
        }

        return $encodings;
    }

    /**
     * @param list<array{rowid:int,key:string,bytes:string,encoding:string}> $currentRows
     * @param list<array{rowid:int,key:string,bytes:string,encoding:string}> $nextRows
     * @return array{encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,comparisonKeyChangedRowids:list<int>}
     */
    private static function matchedSourceChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $encodingChanged = [];
        $bytesChanged = [];
        $keyChanged = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encodingChanged[] = $rowid;
            }
            if ($current[$rowid]['bytes'] !== $row['bytes']) {
                $bytesChanged[] = $rowid;
            }
            if ($current[$rowid]['key'] !== $row['key']) {
                $keyChanged[] = $rowid;
            }
        }

        sort($encodingChanged);
        sort($bytesChanged);
        sort($keyChanged);

        return [
            'encodingChangedRowids' => $encodingChanged,
            'bytesChangedRowids' => $bytesChanged,
            'comparisonKeyChangedRowids' => $keyChanged,
        ];
    }

    /**
     * @param list<int> $currentRowids
     * @param list<int> $nextRowids
     * @param array<int,string> $currentErrors
     * @param array<int,string> $nextErrors
     * @param list<int> $encodingChangedRowids
     * @param list<int> $bytesChangedRowids
     * @param list<int> $comparisonKeyChangedRowids
     * @return list<string>
     */
    private static function reprepareReasons(
        string $currentSource,
        string $nextSource,
        array $currentRowids,
        array $nextRowids,
        array $currentErrors,
        array $nextErrors,
        array $encodingChangedRowids,
        array $bytesChangedRowids,
        array $comparisonKeyChangedRowids,
    ): array {
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentErrors !== $nextErrors) {
            $reasons[] = 'malformed-text';
        }
        if ($currentRowids !== $nextRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($encodingChangedRowids !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($bytesChangedRowids !== []) {
            $reasons[] = 'key-bytes';
        }
        if ($comparisonKeyChangedRowids !== []) {
            $reasons[] = 'comparison-key';
        }

        return $reasons;
    }

    private static function decodeText(string $bytes, int $encoding): string
    {
        if ($encoding === 1) {
            if (preg_match('//u', $bytes) !== 1) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE UTF-8 text payload is malformed');
            }
            return $bytes;
        }
        if (!in_array($encoding, [2, 3], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }
        if (strlen($bytes) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text payload has an odd byte length');
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::unpackUint16(substr($bytes, $offset, 2), $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text payload ends with a high surrogate');
                }
                $low = self::unpackUint16(substr($bytes, $offset + 2, 2), $encoding);
                if ($low < 0xdc00 || $low > 0xdfff) {
                    throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text payload has an unpaired high surrogate');
                }
                $text .= self::utf8FromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($low - 0xdc00));
                $offset += 2;
                continue;
            }
            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text payload has an unpaired low surrogate');
            }
            $text .= self::utf8FromCodepoint($unit);
        }

        return $text;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 RTRIM NOCASE text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function unpackUint16(string $bytes, int $encoding): int
    {
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);

        return $encoding === 2 ? $first | ($second << 8) : ($first << 8) | $second;
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
