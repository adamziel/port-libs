<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext171Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameDuplicateKeyReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@170',
        string $nextSource = 'main.wp_options@171',
        int $currentSchemaCookie = 170,
        int $nextSchemaCookie = 171,
    ): array {
        self::assertToken($lastYielded);

        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);

        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentAfter = self::afterToken($current['matched'], $lastYielded);
        $nextAfter = self::afterToken($next['matched'], $lastYielded);
        $nextBeforeOrAt = self::beforeOrAtToken($next['matched'], $lastYielded);
        $duplicateKeys = self::duplicateKeys($next['matched']);
        $sameRowChanges = self::sameRowChanges($current['matched'], $next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($like['range'] === null) {
            $reasons[] = 'full-scan-like-residual';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ($sameRowChanges as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($duplicateKeys !== []) {
            $reasons[] = 'duplicate-rtrim-nocase-key';
        }
        if ($lastYielded === null) {
            $reasons[] = 'no-yield-token';
        }
        if ($lastYielded !== null) {
            foreach ($nextBeforeOrAt as $row) {
                if (!in_array($row['rowid'], $currentMatched, true)) {
                    $reasons[] = 'entered-before-token';
                    break;
                }
            }
        }

        $requiresReprepare = $reasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next171',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'range' => $like['range'],
            'lastYielded' => $lastYielded,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentMatchedKeys' => self::map($current['matched'], 'key'),
            'nextMatchedKeys' => self::map($next['matched'], 'key'),
            'currentMatchedBytesHex' => self::map($current['matched'], 'bytesHex'),
            'nextMatchedBytesHex' => self::map($next['matched'], 'bytesHex'),
            'currentMatchedEncodings' => self::map($current['matched'], 'encoding'),
            'nextMatchedEncodings' => self::map($next['matched'], 'encoding'),
            'currentAfterTokenRowids' => self::rowids($currentAfter),
            'nextAfterTokenRowids' => self::rowids($nextAfter),
            'nextBeforeOrAtTokenRowids' => self::rowids($nextBeforeOrAt),
            'duplicateRtrimNocaseKeys' => $duplicateKeys,
            'changedKeyRowids' => $sameRowChanges['key-changed'],
            'changedEncodingRowids' => $sameRowChanges['encoding-changed'],
            'changedBytesRowids' => $sameRowChanges['bytes-changed'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'replayInvalidationReasons' => array_values(array_unique($reasons)),
            'mustReprepareBeforeReplay' => $requiresReprepare,
            'safeToReplayFromToken' => !$requiresReprepare,
            'replayPlanRowids' => $requiresReprepare ? $nextMatched : self::rowids($nextAfter),
            'replayPlanMode' => $requiresReprepare ? 'reprepare-from-range-start' : 'continue-after-key-rowid-token',
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-duplicate-key-replay',
                'sqlite-current-source-next171',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source key/rowid/byte replay diagnostics',
            'non_overlap' => 'adds duplicate RTRIM/NOCASE key replay and byte-fingerprint invalidation after next167 fallback/residual behavior; does not repeat Unicode GLOB ranges, UTF-16 malformed insert guard, RHS pattern trimming, or generic LIKE prefix planning',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{matched:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $rtrim = rtrim($text, ' ');
            $key = self::asciiLower($rtrim);
            if ($range !== null && !self::inRange($key, $range)) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false)) {
                continue;
            }
            $matched[] = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'rtrimText' => $rtrim,
                'key' => $key,
                'encoding' => self::encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['option_name_bytes']),
            ];
        }

        usort($matched, self::sortRows(...));
        sort($malformed);
        ksort($errors);

        return ['matched' => $matched, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next171 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next171 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next171 rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token */
    private static function assertToken(?array $token): void
    {
        if ($token === null) {
            return;
        }
        if (!array_key_exists('key', $token) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next171 token requires string key');
        }
        if (!array_key_exists('rowid', $token) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next171 token requires integer rowid');
        }
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, array $range): bool
    {
        return strcmp($key, $range['lowerInclusive']) >= 0
            && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0);
    }

    /** @param array{key:string,rowid:int} $left @param array{key:string,rowid:int} $right */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['key'], $right['key']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return list<array<string,mixed>>
     */
    private static function afterToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strcmp($row['key'], $token['key']) > 0
                || ($row['key'] === $token['key'] && $row['rowid'] > $token['rowid'])
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return list<array<string,mixed>>
     */
    private static function beforeOrAtToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strcmp($row['key'], $token['key']) < 0
                || ($row['key'] === $token['key'] && $row['rowid'] <= $token['rowid'])
        ));
    }

    /** @param list<array{key:string,rowid:int}> $rows @return array<string,list<int>> */
    private static function duplicateKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['key']][] = $row['rowid'];
        }

        return array_filter($keys, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{key-changed:list<int>,encoding-changed:list<int>,bytes-changed:list<int>}
     */
    private static function sameRowChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }
        $changes = ['key-changed' => [], 'encoding-changed' => [], 'bytes-changed' => []];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            foreach (['key' => 'key-changed', 'encoding' => 'encoding-changed', 'bytesHex' => 'bytes-changed'] as $field => $reason) {
                if ($current[$rowid][$field] !== $row[$field]) {
                    $changes[$reason][] = $rowid;
                }
            }
        }

        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
