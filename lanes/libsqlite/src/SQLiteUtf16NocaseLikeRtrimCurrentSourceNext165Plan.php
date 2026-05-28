<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext165Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameResumePlan(
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
        string $currentSource = 'main.wp_options@164',
        string $nextSource = 'main.wp_options@165',
        int $currentSchemaCookie = 164,
        int $nextSchemaCookie = 165,
    ): array {
        self::assertLastYielded($lastYielded);

        $base = SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext162Plan::wordpressOptionNameNormalizedPatternPlan(
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
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentKeys = self::keys(self::decodedRtrimTexts($currentRows), $base['currentMatchedRowids']);
        $nextKeys = self::keys(self::decodedRtrimTexts($nextRows), $base['nextMatchedRowids']);
        $currentAfter = self::afterToken($currentKeys, $lastYielded);
        $nextAfter = self::afterToken($nextKeys, $lastYielded);
        $nextBeforeOrAt = self::beforeOrAtToken($nextKeys, $lastYielded);
        $retainedAfter = array_values(array_intersect($currentAfter, $nextAfter));
        $enteredAfter = array_values(array_diff($nextAfter, $currentAfter));
        $exitedAfter = array_values(array_diff($currentAfter, $nextAfter));
        $movedBeforeToken = array_values(array_intersect($currentAfter, $nextBeforeOrAt));
        $newBeforeToken = array_values(array_diff($nextBeforeOrAt, $base['currentMatchedRowids']));

        $resumeReasons = [];
        if ($lastYielded === null) {
            $resumeReasons[] = 'no-yield-token';
        }
        $structuralSemanticReasons = array_values(array_diff(
            $base['semanticInvalidationReasons'],
            ['candidate-rowset', 'matched-rowset', 'rtrim-false-positive-rowset'],
        ));
        if ($structuralSemanticReasons !== []) {
            $resumeReasons[] = 'semantic-invalidation';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $resumeReasons[] = 'malformed-text';
        }
        if ($newBeforeToken !== []) {
            $resumeReasons[] = 'entered-before-token';
        }
        if ($movedBeforeToken !== []) {
            $resumeReasons[] = 'retained-moved-across-token';
        }
        if ($base['currentIndexUsable'] === false || $base['nextIndexUsable'] === false) {
            $resumeReasons[] = 'unusable-prefix-range';
        }

        $mustReprepare = $resumeReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next165',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'normalizesPreparedPatternBytes' => true,
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentPattern' => $base['currentPattern'],
            'nextPattern' => $base['nextPattern'],
            'sameDecodedPattern' => $base['sameDecodedPattern'],
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'sameDecodedEscape' => $base['sameDecodedEscape'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRange' => $base['currentRtrimRange'],
            'nextRange' => $base['nextRtrimRange'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'lastYielded' => $lastYielded,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedKeys' => $currentKeys,
            'nextMatchedKeys' => $nextKeys,
            'currentAfterTokenRowids' => $currentAfter,
            'nextAfterTokenRowids' => $nextAfter,
            'nextBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'retainedAfterTokenRowids' => $retainedAfter,
            'enteredAfterTokenRowids' => $enteredAfter,
            'exitedAfterTokenRowids' => $exitedAfter,
            'newBeforeTokenRowids' => $newBeforeToken,
            'retainedMovedAcrossTokenRowids' => $movedBeforeToken,
            'byteReprepareReasons' => $base['byteReprepareReasons'],
            'semanticInvalidationReasons' => $base['semanticInvalidationReasons'],
            'baseInvalidationReasons' => $base['baseInvalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'resumeReasons' => array_values(array_unique($resumeReasons)),
            'mustReprepareBeforeResume' => $mustReprepare,
            'safeToResumeFromToken' => !$mustReprepare,
            'resumePlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $nextAfter,
            'resumePlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-last-yielded-key-rowid',
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-current-source-next165',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode/pattern normalization, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds current-source resume-token diagnostics',
        ];
    }

    /** @param array{key:string,rowid:int}|null $lastYielded */
    private static function assertLastYielded(?array $lastYielded): void
    {
        if ($lastYielded === null) {
            return;
        }
        if (!array_key_exists('key', $lastYielded) || !is_string($lastYielded['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next165 resume token requires string key');
        }
        if (!array_key_exists('rowid', $lastYielded) || !is_int($lastYielded['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next165 resume token requires integer rowid');
        }
    }

    /** @param array<int,string> $texts @param list<int> $rowids @return array<int,string> */
    private static function keys(array $texts, array $rowids): array
    {
        $keys = [];
        foreach ($rowids as $rowid) {
            $keys[$rowid] = self::asciiLower($texts[$rowid]);
        }
        uasort($keys, static fn (string $left, string $right): int => strcmp($left, $right));

        return $keys;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function decodedRtrimTexts(array $rows): array
    {
        $texts = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next165 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next165 rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next165 rows require integer text_encoding');
            }

            try {
                $texts[$row['option_id']] = rtrim(SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']), ' ');
            } catch (\InvalidArgumentException) {
            }
        }

        return $texts;
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function afterToken(array $keys, ?array $token): array
    {
        if ($token === null) {
            return array_map('intval', array_keys($keys));
        }

        $rowids = [];
        foreach ($keys as $rowid => $key) {
            if (strcmp($key, $token['key']) > 0 || ($key === $token['key'] && $rowid > $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
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
