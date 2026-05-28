<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext169Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameYieldPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        ?array $lastYielded = null,
        int $pageSize = 3,
        string $currentSource = 'main.wp_options@168',
        string $nextSource = 'main.wp_options@169',
        int $currentSchemaCookie = 168,
        int $nextSchemaCookie = 169,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next169 yield page size must be positive');
        }

        $resume = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext165Plan::wordpressOptionNameResumePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $nextKeys = self::sortedKeys($nextRows, $resume['nextMatchedRowids']);
        $yieldRowids = array_slice($resume['resumePlanRowids'], 0, $pageSize);
        $deferredRowids = array_slice($resume['resumePlanRowids'], $pageSize);
        $yieldKeys = self::subset($nextKeys, $yieldRowids);
        $deferredKeys = self::subset($nextKeys, $deferredRowids);
        $highWaterToken = self::tokenForLast($yieldKeys);

        $previouslyYielded = self::beforeOrAtToken($nextKeys, $lastYielded);
        $wouldDuplicate = array_values(array_intersect($yieldRowids, $previouslyYielded));
        $staleRetained = array_values(array_intersect($resume['currentAfterTokenRowids'], $previouslyYielded));

        $restartReasons = $resume['resumeReasons'];
        if ($wouldDuplicate !== []) {
            $restartReasons[] = 'would-duplicate-yield';
        }
        if ($staleRetained !== []) {
            $restartReasons[] = 'retained-row-became-before-token';
        }

        $mustRestart = $resume['mustReprepareBeforeResume'] || $wouldDuplicate !== [] || $staleRetained !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next169',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'baseStatus' => $resume['status'],
            'currentSource' => $resume['currentSource'],
            'nextSource' => $resume['nextSource'],
            'currentSchemaCookie' => $resume['currentSchemaCookie'],
            'nextSchemaCookie' => $resume['nextSchemaCookie'],
            'currentPattern' => $resume['currentPattern'],
            'nextPattern' => $resume['nextPattern'],
            'sameDecodedPattern' => $resume['sameDecodedPattern'],
            'currentEscape' => $resume['currentEscape'],
            'nextEscape' => $resume['nextEscape'],
            'sameDecodedEscape' => $resume['sameDecodedEscape'],
            'currentRange' => $resume['currentRange'],
            'nextRange' => $resume['nextRange'],
            'currentIndexUsable' => $resume['currentIndexUsable'],
            'nextIndexUsable' => $resume['nextIndexUsable'],
            'lastYielded' => $lastYielded,
            'pageSize' => $pageSize,
            'currentMatchedRowids' => $resume['currentMatchedRowids'],
            'nextMatchedRowids' => $resume['nextMatchedRowids'],
            'currentAfterTokenRowids' => $resume['currentAfterTokenRowids'],
            'nextAfterTokenRowids' => $resume['nextAfterTokenRowids'],
            'resumePlanMode' => $resume['resumePlanMode'],
            'resumePlanRowids' => $resume['resumePlanRowids'],
            'yieldMode' => $mustRestart ? 'restart-then-yield-page' : 'continue-yield-page',
            'yieldedRowids' => $yieldRowids,
            'yieldedKeys' => $yieldKeys,
            'deferredRowids' => $deferredRowids,
            'deferredKeys' => $deferredKeys,
            'highWaterToken' => $highWaterToken,
            'hasMore' => $deferredRowids !== [],
            'previouslyYieldedRowids' => $previouslyYielded,
            'wouldDuplicateRowids' => $wouldDuplicate,
            'staleRetainedBeforeTokenRowids' => $staleRetained,
            'newBeforeTokenRowids' => $resume['newBeforeTokenRowids'],
            'retainedMovedAcrossTokenRowids' => $resume['retainedMovedAcrossTokenRowids'],
            'byteReprepareReasons' => $resume['byteReprepareReasons'],
            'semanticInvalidationReasons' => $resume['semanticInvalidationReasons'],
            'baseInvalidationReasons' => $resume['baseInvalidationReasons'],
            'resumeReasons' => $resume['resumeReasons'],
            'restartReasons' => array_values(array_unique($restartReasons)),
            'mustRestartBeforeYield' => $mustRestart,
            'safeToContinueYield' => !$mustRestart,
            'currentMalformedRowids' => $resume['currentMalformedRowids'],
            'nextMalformedRowids' => $resume['nextMalformedRowids'],
            'currentErrors' => $resume['currentErrors'],
            'nextErrors' => $resume['nextErrors'],
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-yield-high-water-token',
                'sqlite-current-source-next169',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode/pattern normalization, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds bounded yield-page/high-water-token diagnostics',
        ];
    }

    /** @param list<array<string,mixed>> $rows @param list<int> $matchedRowids @return array<int,string> */
    private static function sortedKeys(array $rows, array $matchedRowids): array
    {
        $wanted = array_fill_keys($matchedRowids, true);
        $keys = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next169 rows require integer option_id');
            }
            if (!isset($wanted[$row['option_id']])) {
                continue;
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next169 rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next169 rows require integer text_encoding');
            }

            try {
                $keys[$row['option_id']] = self::asciiLower(rtrim(SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']), ' '));
            } catch (\InvalidArgumentException) {
            }
        }
        uasort($keys, static fn (string $left, string $right): int => strcmp($left, $right));

        return $keys;
    }

    /** @param array<int,string> $keys @param list<int> $rowids @return array<int,string> */
    private static function subset(array $keys, array $rowids): array
    {
        $subset = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $keys)) {
                $subset[$rowid] = $keys[$rowid];
            }
        }

        return $subset;
    }

    /** @param array<int,string> $keys @return array{key:string,rowid:int}|null */
    private static function tokenForLast(array $keys): ?array
    {
        if ($keys === []) {
            return null;
        }
        $rowid = (int) array_key_last($keys);

        return [
            'key' => $keys[$rowid],
            'rowid' => $rowid,
        ];
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function beforeOrAtToken(array $keys, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        $rowids = [];
        foreach ($keys as $rowid => $key) {
            if (strcmp($key, $token['key']) < 0 || ($key === $token['key'] && $rowid <= $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
