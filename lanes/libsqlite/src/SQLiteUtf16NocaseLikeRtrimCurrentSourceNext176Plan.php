<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePeerYieldPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        int $pageSize = 4,
        string $currentSource = 'main.wp_options@175',
        string $nextSource = 'main.wp_options@176',
        int $currentSchemaCookie = 175,
        int $nextSchemaCookie = 176,
    ): array {
        self::assertLastYielded($lastYielded);
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 yield page size must be positive');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);

        $currentMatched = self::matchedIndex($current['matched']);
        $nextMatched = self::matchedIndex($next['matched']);
        $currentMatchedRowids = array_keys($currentMatched);
        $nextMatchedRowids = array_keys($nextMatched);
        $nextAfterToken = self::afterToken($nextMatched, $lastYielded);
        $yieldedRowids = array_slice($nextAfterToken, 0, $pageSize);
        $deferredRowids = array_slice($nextAfterToken, $pageSize);
        $highWaterToken = self::tokenForLast($nextMatched, $yieldedRowids);
        $duplicatePeerGroups = self::peerGroups($nextMatched);
        $peerGroupsStraddlingToken = self::straddlingPeerGroups($duplicatePeerGroups, $lastYielded);
        $peerGroupsStraddlingYield = self::straddlingYieldGroups($duplicatePeerGroups, $yieldedRowids, $deferredRowids);
        $currentPeerGroups = self::peerGroups($currentMatched);
        $peerGroupChanges = self::peerGroupChanges($currentPeerGroups, $duplicatePeerGroups);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $reasons[] = 'unusable-nocase-prefix-range';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($peerGroupChanges !== []) {
            $reasons[] = 'peer-group-rowid-order';
        }
        if ($peerGroupsStraddlingToken !== []) {
            $reasons[] = 'peer-group-straddles-resume-token';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next176',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentMatchedKeys' => self::keys($currentMatched),
            'nextMatchedKeys' => self::keys($nextMatched),
            'currentDuplicatePeerGroups' => $currentPeerGroups,
            'nextDuplicatePeerGroups' => $duplicatePeerGroups,
            'peerGroupChanges' => $peerGroupChanges,
            'lastYielded' => $lastYielded,
            'nextAfterTokenRowids' => $nextAfterToken,
            'pageSize' => $pageSize,
            'yieldedRowids' => $yieldedRowids,
            'deferredRowids' => $deferredRowids,
            'highWaterToken' => $highWaterToken,
            'hasMore' => $deferredRowids !== [],
            'peerGroupsStraddlingToken' => $peerGroupsStraddlingToken,
            'peerGroupsStraddlingYieldPage' => $peerGroupsStraddlingYield,
            'usesRowidTieBreaker' => true,
            'safeToResumeInsidePeerGroup' => $peerGroupsStraddlingToken === [],
            'safeToPersistHighWaterToken' => $highWaterToken !== null && $peerGroupsStraddlingYield === [],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-rowid-tiebreaker',
                'sqlite-current-source-next176',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds rowid-tied duplicate peer yield diagnostics',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimKey' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($malformed);
        ksort($errors);

        $matched = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($entry['rtrimKey'], $pattern, $escape, false)) {
                continue;
            }
            $matched[] = $entry;
        }

        return [
            'decoded' => $decoded,
            'matched' => $matched,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int}|null $lastYielded */
    private static function assertLastYielded(?array $lastYielded): void
    {
        if ($lastYielded === null) {
            return;
        }
        if (!array_key_exists('key', $lastYielded) || !is_string($lastYielded['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 resume token requires string key');
        }
        if (!array_key_exists('rowid', $lastYielded) || !is_int($lastYielded['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next176 resume token requires integer rowid');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $matched @return array<int,array{key:string,rowid:int,encoding:string,bytesHex:string}> */
    private static function matchedIndex(array $matched): array
    {
        $indexed = [];
        foreach ($matched as $row) {
            $indexed[$row['rowid']] = [
                'key' => $row['nocaseKey'],
                'rowid' => $row['rowid'],
                'encoding' => $row['encoding'],
                'bytesHex' => $row['bytesHex'],
            ];
        }

        return $indexed;
    }

    /** @param array<int,array{key:string}> $matched @return array<int,string> */
    private static function keys(array $matched): array
    {
        $keys = [];
        foreach ($matched as $rowid => $row) {
            $keys[$rowid] = $row['key'];
        }

        return $keys;
    }

    /** @param array<int,array{key:string,rowid:int}> $matched @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function afterToken(array $matched, ?array $token): array
    {
        if ($token === null) {
            return array_map('intval', array_keys($matched));
        }

        $rowids = [];
        foreach ($matched as $rowid => $entry) {
            if (strcmp($entry['key'], $token['key']) > 0 || ($entry['key'] === $token['key'] && $entry['rowid'] > $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    /** @param array<int,array{key:string,rowid:int}> $matched @param list<int> $rowids @return array{key:string,rowid:int}|null */
    private static function tokenForLast(array $matched, array $rowids): ?array
    {
        if ($rowids === []) {
            return null;
        }
        $rowid = $rowids[array_key_last($rowids)];

        return [
            'key' => $matched[$rowid]['key'],
            'rowid' => $rowid,
        ];
    }

    /** @param array<int,array{key:string,rowid:int,encoding:string,bytesHex:string}> $matched @return array<string,array{key:string,rowids:list<int>,encodings:array<int,string>,bytesHex:array<int,string>}> */
    private static function peerGroups(array $matched): array
    {
        $groups = [];
        foreach ($matched as $rowid => $entry) {
            $key = $entry['key'];
            $groups[$key]['key'] = $key;
            $groups[$key]['rowids'][] = (int) $rowid;
            $groups[$key]['encodings'][(int) $rowid] = $entry['encoding'];
            $groups[$key]['bytesHex'][(int) $rowid] = $entry['bytesHex'];
        }

        return array_filter($groups, static fn (array $group): bool => count($group['rowids']) > 1);
    }

    /**
     * @param array<string,array{rowids:list<int>}> $current
     * @param array<string,array{rowids:list<int>}> $next
     * @return array<string,array{currentRowids:list<int>,nextRowids:list<int>}>
     */
    private static function peerGroupChanges(array $current, array $next): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($next))) as $key) {
            $currentRowids = $current[$key]['rowids'] ?? [];
            $nextRowids = $next[$key]['rowids'] ?? [];
            if ($currentRowids !== $nextRowids) {
                $changes[$key] = [
                    'currentRowids' => $currentRowids,
                    'nextRowids' => $nextRowids,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param array<string,array{key:string,rowids:list<int>}> $groups
     * @param array{key:string,rowid:int}|null $token
     * @return array<string,array{beforeOrAt:list<int>,after:list<int>}>
     */
    private static function straddlingPeerGroups(array $groups, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        $straddling = [];
        foreach ($groups as $key => $group) {
            if ($key !== $token['key']) {
                continue;
            }
            $before = array_values(array_filter($group['rowids'], static fn (int $rowid): bool => $rowid <= $token['rowid']));
            $after = array_values(array_filter($group['rowids'], static fn (int $rowid): bool => $rowid > $token['rowid']));
            if ($before !== [] && $after !== []) {
                $straddling[$key] = [
                    'beforeOrAt' => $before,
                    'after' => $after,
                ];
            }
        }

        return $straddling;
    }

    /**
     * @param array<string,array{rowids:list<int>}> $groups
     * @param list<int> $yielded
     * @param list<int> $deferred
     * @return array<string,array{yielded:list<int>,deferred:list<int>}>
     */
    private static function straddlingYieldGroups(array $groups, array $yielded, array $deferred): array
    {
        $straddling = [];
        foreach ($groups as $key => $group) {
            $yieldedPeers = array_values(array_intersect($group['rowids'], $yielded));
            $deferredPeers = array_values(array_intersect($group['rowids'], $deferred));
            if ($yieldedPeers !== [] && $deferredPeers !== []) {
                $straddling[$key] = [
                    'yielded' => $yieldedPeers,
                    'deferred' => $deferredPeers,
                ];
            }
        }

        return $straddling;
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
