<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext193Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameLimitOffsetPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        int $limit = 3,
        int $offset = 2,
        string $currentSource = 'main.wp_options@192',
        string $nextSource = 'main.wp_options@193',
        int $currentSchemaCookie = 192,
        int $nextSchemaCookie = 193,
    ): array {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next193 LIMIT must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next193 OFFSET must be non-negative');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::orderedRows($base['currentNocaseKeys'], $base['currentMatchedRowids']);
        $nextOrdered = self::orderedRows($base['nextNocaseKeys'], $base['nextMatchedRowids']);
        $currentSkipped = array_slice($currentOrdered, 0, $offset);
        $nextSkipped = array_slice($nextOrdered, 0, $offset);
        $currentWindow = array_slice($currentOrdered, $offset, $limit);
        $nextWindow = array_slice($nextOrdered, $offset, $limit);
        $currentAfterWindow = array_slice($currentOrdered, $offset + $limit);
        $nextAfterWindow = array_slice($nextOrdered, $offset + $limit);

        $windowEntered = array_values(array_diff($nextWindow, $currentWindow));
        $windowExited = array_values(array_diff($currentWindow, $nextWindow));
        $skippedEntered = array_values(array_diff($nextSkipped, $currentSkipped));
        $skippedExited = array_values(array_diff($currentSkipped, $nextSkipped));
        $beforeWindowChanged = $currentSkipped !== $nextSkipped;

        $reasons = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'source-or-schema-changed';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (!$base['indexUsable'] || !$base['usesPrefixRangeCursor']) {
            $reasons[] = 'prefix-range-unusable';
        }
        if ($beforeWindowChanged) {
            $reasons[] = 'offset-prefix-rowset-changed';
        }
        if ($windowEntered !== [] || $windowExited !== []) {
            $reasons[] = 'limit-window-rowset-changed';
        }
        if ($base['rtrimResidualChangedRowids'] !== []) {
            $reasons[] = 'rtrim-like-residual-changed';
        }

        $resumeSafe = $reasons === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next193',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? LIMIT ? OFFSET ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'limit' => $limit,
            'offset' => $offset,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentOrderedMatchedRowids' => $currentOrdered,
            'nextOrderedMatchedRowids' => $nextOrdered,
            'currentSkippedRowids' => $currentSkipped,
            'nextSkippedRowids' => $nextSkipped,
            'currentLimitWindowRowids' => $currentWindow,
            'nextLimitWindowRowids' => $nextWindow,
            'currentAfterWindowRowids' => $currentAfterWindow,
            'nextAfterWindowRowids' => $nextAfterWindow,
            'skippedEnteredRowids' => $skippedEntered,
            'skippedExitedRowids' => $skippedExited,
            'limitWindowEnteredRowids' => $windowEntered,
            'limitWindowExitedRowids' => $windowExited,
            'offsetPrefixChanged' => $beforeWindowChanged,
            'currentWindowTexts' => self::selectMap($base['currentMatchedTexts'], $currentWindow),
            'nextWindowTexts' => self::selectMap($base['nextMatchedTexts'], $nextWindow),
            'currentSkippedTexts' => self::selectMap($base['currentMatchedTexts'], $currentSkipped),
            'nextSkippedTexts' => self::selectMap($base['nextMatchedTexts'], $nextSkipped),
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'limitOffsetUnsafeReasons' => array_values(array_unique($reasons)),
            'limitOffsetResumeSafe' => $resumeSafe,
            'mustReprepareBeforeLimitOffsetResume' => !$resumeSafe,
            'replayPlanMode' => $resumeSafe ? 'continue-after-limit-window' : 'recompute-limit-offset-window',
            'replayPlanRowids' => $resumeSafe ? $nextAfterWindow : $nextWindow,
            'offsetCountsDecodedRowsNotBytes' => true,
            'limitWindowUsesRtrimNocaseOrder' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesBeforeLimitOffset' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-limit-offset-window',
                'sqlite-current-source-next193',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix ranges, RTRIM expression keys, and adds LIMIT/OFFSET current-source replay diagnostics',
            'non_overlap' => 'next193 adds LIMIT/OFFSET window replay diagnostics for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted next189 peer-window rowid tie-breakers, deleted-token resume, escaped residual token, case-sensitive LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array<int,string> $keys @param list<int> $rowids @return list<int> */
    private static function orderedRows(array $keys, array $rowids): array
    {
        $ordered = $rowids;
        usort($ordered, static function (int $left, int $right) use ($keys): int {
            $keyCompare = strcmp($keys[$left] ?? '', $keys[$right] ?? '');
            if ($keyCompare !== 0) {
                return $keyCompare;
            }

            return $left <=> $right;
        });

        return $ordered;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }
}
