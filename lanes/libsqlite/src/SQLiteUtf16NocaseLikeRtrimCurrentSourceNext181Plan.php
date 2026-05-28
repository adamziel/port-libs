<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePeerReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@180',
        string $nextSource = 'main.wp_options@181',
        int $currentSchemaCookie = 180,
        int $nextSchemaCookie = 181,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Plan::wordpressOptionNameCanonicalTokenPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentRowsByRowid = self::scanByRowid($currentRows, $pattern, $escape, $base['range']);
        $nextRowsByRowid = self::scanByRowid($nextRows, $pattern, $escape, $base['range']);
        $token = is_array($base['normalizedLastYielded']) ? $base['normalizedLastYielded'] : null;
        $peerKey = $token['key'] ?? null;
        $currentPeerRowids = $peerKey === null ? [] : self::peerRowids($currentRowsByRowid, $peerKey);
        $nextPeerRowids = $peerKey === null ? [] : self::peerRowids($nextRowsByRowid, $peerKey);
        $peerAfterTokenRowids = $token === null ? [] : array_values(array_filter(
            $nextPeerRowids,
            static fn (int $rowid): bool => $rowid > $token['rowid']
        ));
        $peerBeforeOrAtTokenRowids = $token === null ? [] : array_values(array_filter(
            $nextPeerRowids,
            static fn (int $rowid): bool => $rowid <= $token['rowid']
        ));
        $afterPeerRowids = $peerKey === null ? $base['nextMatchedRowids'] : array_values(array_filter(
            $base['nextMatchedRowids'],
            static fn (int $rowid): bool => isset($nextRowsByRowid[$rowid]) && strcmp($nextRowsByRowid[$rowid]['key'], $peerKey) > 0
        ));

        $sameSource = $base['currentSource'] === $base['nextSource'] && $base['currentSchemaCookie'] === $base['nextSchemaCookie'];
        $stableToken = $token !== null && $base['tokenNormalizationReasons'] === [] && $base['tokenFingerprintReasons'] === [];
        $stablePeer = $currentPeerRowids === $nextPeerRowids;
        $peerSafeReasons = [];
        if (!$sameSource) {
            $peerSafeReasons[] = 'source-or-schema-changed';
        }
        if (!$stableToken) {
            $peerSafeReasons[] = 'yield-token-not-stable';
        }
        if (!$stablePeer) {
            $peerSafeReasons[] = 'peer-rowset-changed';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $peerSafeReasons[] = 'malformed-text';
        }

        $peerContinuationSafe = $peerSafeReasons === [];
        $sameKeyReplayRowids = $peerContinuationSafe ? $peerAfterTokenRowids : [];
        $remainingAfterPeerRowids = $peerContinuationSafe ? $afterPeerRowids : [];
        $replayPlanRowids = $peerContinuationSafe
            ? array_values(array_merge($sameKeyReplayRowids, $remainingAfterPeerRowids))
            : $base['replayPlanRowids'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next181',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'normalizedLastYielded' => $token,
            'peerKey' => $peerKey,
            'currentPeerRowids' => $currentPeerRowids,
            'nextPeerRowids' => $nextPeerRowids,
            'peerBeforeOrAtTokenRowids' => $peerBeforeOrAtTokenRowids,
            'peerAfterTokenRowids' => $peerAfterTokenRowids,
            'sameKeyReplayRowids' => $sameKeyReplayRowids,
            'remainingAfterPeerRowids' => $remainingAfterPeerRowids,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedKeys' => self::map($nextRowsByRowid, 'key'),
            'nextMatchedRtrimText' => self::map($nextRowsByRowid, 'rtrimText'),
            'nextMatchedEncodings' => self::map($nextRowsByRowid, 'encoding'),
            'duplicateRtrimNocaseKeys' => self::duplicateKeys($nextRowsByRowid),
            'tokenNormalizationReasons' => $base['tokenNormalizationReasons'],
            'tokenFingerprintReasons' => $base['tokenFingerprintReasons'],
            'baseReplayInvalidationReasons' => $base['baseReplayInvalidationReasons'],
            'peerReplayUnsafeReasons' => $peerSafeReasons,
            'peerContinuationSafe' => $peerContinuationSafe,
            'mustReprepareBeforePeerReplay' => !$peerContinuationSafe,
            'safeToContinueWithinPeerGroup' => $peerContinuationSafe,
            'replayPlanMode' => $peerContinuationSafe ? 'continue-after-key-rowid-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replayPlanRowids,
            'tokenUsesRowidTieBreakerForEqualRtrimNocaseKeys' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-peer-replay',
                'sqlite-current-source-next181',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, and next178 canonical byte-token validation',
            'non_overlap' => 'adds same-key peer replay for stable UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted next178 canonical token validation, next177 Unicode wildcard residuals, next171 duplicate-key invalidation, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array<int,array{rowid:int,text:string,rtrimText:string,key:string,encoding:string,bytesHex:string}>
     */
    private static function scanByRowid(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException) {
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

        return $matched;
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next181 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next181 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next181 rows require integer text_encoding');
        }
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

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $field): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$field];
        }

        return $mapped;
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return array<string,list<int>> */
    private static function duplicateKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['key']][] = $row['rowid'];
        }

        return array_filter($keys, static fn (array $rowids): bool => count($rowids) > 1);
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
