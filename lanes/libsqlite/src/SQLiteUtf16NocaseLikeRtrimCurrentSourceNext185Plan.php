<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameDeletedTokenResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@184',
        string $nextSource = 'main.wp_options@185',
        int $currentSchemaCookie = 184,
        int $nextSchemaCookie = 185,
    ): array {
        $rangePlan = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $range = $rangePlan['range'];
        $current = self::scan($currentRows, $pattern, $escape, $range);
        $next = self::scan($nextRows, $pattern, $escape, $range);
        $token = self::normalizeToken($lastYielded);
        $tokenKey = $token['key'] ?? null;
        $tokenRowid = $token['rowid'] ?? null;

        $currentPeerRowids = $tokenKey === null ? [] : self::peerRowids($current['matched'], $tokenKey);
        $nextPeerRowids = $tokenKey === null ? [] : self::peerRowids($next['matched'], $tokenKey);
        $currentBeforeOrAt = $tokenKey === null || $tokenRowid === null ? [] : self::peerBeforeOrAt($currentPeerRowids, $tokenRowid);
        $nextBeforeOrAt = $tokenKey === null || $tokenRowid === null ? [] : self::peerBeforeOrAt($nextPeerRowids, $tokenRowid);
        $currentAfter = $tokenKey === null || $tokenRowid === null ? [] : self::peerAfter($currentPeerRowids, $tokenRowid);
        $nextAfter = $tokenKey === null || $tokenRowid === null ? [] : self::peerAfter($nextPeerRowids, $tokenRowid);
        $tokenExited = $tokenRowid !== null && !isset($next['matched'][$tokenRowid]);

        $expectedNextBeforeOrAt = array_values(array_diff($currentBeforeOrAt, $tokenRowid === null ? [] : [$tokenRowid]));
        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($token === null) {
            $unsafe[] = 'yield-token-missing';
        } elseif ($token['normalizationReasons'] !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        if (!$tokenExited) {
            $unsafe[] = 'yield-token-not-deleted';
        }
        if ($current['errors'] !== [] || $next['errors'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if ($tokenExited && $tokenKey !== null && $nextBeforeOrAt !== $expectedNextBeforeOrAt) {
            $unsafe[] = 'peer-before-token-changed';
        }
        if ($tokenExited && $tokenKey !== null && count($nextAfter) < count($currentAfter)) {
            $unsafe[] = 'peer-after-token-lost-row';
        }

        $safe = $unsafe === [];
        $samePeerReplay = $safe && $tokenKey !== null && $tokenRowid !== null ? $nextAfter : [];
        $afterPeerReplay = $safe && $tokenKey !== null ? self::afterKeyRowids($next['matched'], $tokenKey) : [];
        $replayRowids = $safe ? array_values(array_merge($samePeerReplay, $afterPeerReplay)) : self::rowids($next['matched']);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next185',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'range' => $range,
            'prefix' => $rangePlan['prefix'],
            'indexUsable' => $rangePlan['indexUsable'],
            'rejectedReason' => $rangePlan['rejectedReason'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'normalizedLastYielded' => $token === null ? null : [
                'key' => $token['key'],
                'rowid' => $token['rowid'],
                'bytesHex' => $token['bytesHex'],
                'encoding' => $token['encoding'],
            ],
            'tokenNormalizationReasons' => $token['normalizationReasons'] ?? ['yield-token-missing'],
            'deletedTokenRowid' => $tokenExited ? $tokenRowid : null,
            'currentMatchedRowids' => self::rowids($current['matched']),
            'nextMatchedRowids' => self::rowids($next['matched']),
            'currentMatchedKeys' => self::map($current['matched'], 'key'),
            'nextMatchedKeys' => self::map($next['matched'], 'key'),
            'currentMatchedRtrimText' => self::map($current['matched'], 'rtrimText'),
            'nextMatchedRtrimText' => self::map($next['matched'], 'rtrimText'),
            'currentMatchedEncodings' => self::map($current['matched'], 'encoding'),
            'nextMatchedEncodings' => self::map($next['matched'], 'encoding'),
            'currentMatchedBytesHex' => self::map($current['matched'], 'bytesHex'),
            'nextMatchedBytesHex' => self::map($next['matched'], 'bytesHex'),
            'currentPeerRowids' => $currentPeerRowids,
            'nextPeerRowids' => $nextPeerRowids,
            'currentPeerBeforeOrAtTokenRowids' => $currentBeforeOrAt,
            'nextPeerBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'expectedNextPeerBeforeOrAtTokenRowids' => $expectedNextBeforeOrAt,
            'currentPeerAfterTokenRowids' => $currentAfter,
            'nextPeerAfterTokenRowids' => $nextAfter,
            'samePeerReplayRowids' => $samePeerReplay,
            'afterPeerReplayRowids' => $afterPeerReplay,
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'resumeUnsafeReasons' => $unsafe,
            'deletedTokenResumeSafe' => $safe,
            'mustReprepareBeforeDeletedTokenResume' => !$safe,
            'safeToResumeAfterDeletedToken' => $safe,
            'replayPlanMode' => $safe ? 'continue-after-deleted-key-rowid-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replayRowids,
            'tokenUsesRowidBoundaryAfterDeletion' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-deleted-token-resume',
                'sqlite-current-source-next185',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, and key/rowid yield-token replay diagnostics',
            'non_overlap' => 'adds deleted yielded-token resume checks for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted ESCAPE operand validation next182, equal-peer replay next181, canonical token fingerprint next175, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{matched:array<int,array{rowid:int,text:string,rtrimText:string,key:string,encoding:string,bytesHex:string}>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $rtrim = rtrim($text, ' ');
            $key = self::asciiLower($rtrim);
            if ($range !== null && (strcmp($key, $range['lowerInclusive']) < 0 || ($range['upperBound'] !== null && strcmp($key, $range['upperBound']) >= 0))) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false)) {
                continue;
            }
            $matched[$row['option_id']] = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'rtrimText' => $rtrim,
                'key' => $key,
                'encoding' => self::encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['option_name_bytes']),
            ];
        }

        uasort($matched, static function (array $left, array $right): int {
            $comparison = strcmp($left['key'], $right['key']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return ['matched' => $matched, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next185 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next185 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next185 rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token @return array{key:string,rowid:int,bytesHex:?string,encoding:?string,normalizationReasons:list<string>}|null */
    private static function normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }

        $key = self::asciiLower(rtrim($token['key'], ' '));
        $reasons = [];
        if ($key !== $token['key']) {
            $reasons[] = 'token-key-not-canonical';
        }

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'bytesHex' => $token['bytesHex'] ?? null,
            'encoding' => $token['encoding'] ?? null,
            'normalizationReasons' => $reasons,
        ];
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return list<int> */
    private static function peerRowids(array $rows, string $key): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if ($row['key'] === $key) {
                $rowids[] = $row['rowid'];
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<int> $rowids @return list<int> */
    private static function peerBeforeOrAt(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid <= $tokenRowid));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function peerAfter(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid > $tokenRowid));
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return list<int> */
    private static function afterKeyRowids(array $rows, string $key): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (strcmp($row['key'], $key) > 0) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    /** @param array<int,array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $field): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$field];
        }

        return $mapped;
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
