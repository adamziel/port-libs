<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameAsciiPrefixRangePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@182',
        string $nextSource = 'main.wp_options@183',
        int $currentSchemaCookie = 182,
        int $nextSchemaCookie = 183,
    ): array {
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Plan::wordpressOptionNameNonAsciiPrefixPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentCandidates = $plan['currentCandidateRowids'];
        $nextCandidates = $plan['nextCandidateRowids'];
        $currentMatched = $plan['currentMatchedRowids'];
        $nextMatched = $plan['nextMatchedRowids'];

        $plan['status'] = 'utf16-nocase-like-rtrim-current-source-next183';
        $plan['expression'] = 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?';
        $plan['rangeLowerInclusive'] = $plan['range']['lowerInclusive'] ?? null;
        $plan['rangeUpperBound'] = $plan['range']['upperBound'] ?? null;
        $plan['usesPrefixRangeCursor'] = $plan['indexUsable'] && !$plan['usesFullScanFallback'];
        $plan['usesFullScanFallback'] = false;
        $plan['rejectedReason'] = null;
        $plan['currentRangeRetainedRowids'] = array_values(array_intersect($currentCandidates, $nextCandidates));
        $plan['currentRangeExitedRowids'] = array_values(array_diff($currentCandidates, $nextCandidates));
        $plan['nextRangeEnteredRowids'] = array_values(array_diff($nextCandidates, $currentCandidates));
        $plan['matchedRetainedRowids'] = array_values(array_intersect($currentMatched, $nextMatched));
        $plan['matchedExitedRowids'] = array_values(array_diff($currentMatched, $nextMatched));
        $plan['matchedEnteredRowids'] = array_values(array_diff($nextMatched, $currentMatched));
        $plan['currentRangeFalsePositiveRowids'] = $plan['currentFalsePositiveRowids'];
        $plan['nextRangeFalsePositiveRowids'] = $plan['nextFalsePositiveRowids'];
        $plan['currentExcludedDecodedRowids'] = array_values(array_diff($plan['currentDecodedRowids'], $currentCandidates));
        $plan['nextExcludedDecodedRowids'] = array_values(array_diff($plan['nextDecodedRowids'], $nextCandidates));
        $plan['currentMatchedTexts'] = self::selectMap($plan['currentRtrimTexts'], $currentMatched);
        $plan['nextMatchedTexts'] = self::selectMap($plan['nextRtrimTexts'], $nextMatched);
        $plan['rtrimResidualChangedRowids'] = array_values(array_unique(array_merge(
            $plan['changedRtrimRowids'],
            $plan['matchedEnteredRowids'],
            $plan['matchedExitedRowids'],
        )));
        sort($plan['rtrimResidualChangedRowids']);
        $plan['staleRangeCursorRisk'] = $plan['cursorInvalidated'] && (
            $plan['currentRangeExitedRowids'] !== []
            || $plan['nextRangeEnteredRowids'] !== []
            || $plan['rtrimResidualChangedRowids'] !== []
        );
        $plan['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-residual-match',
            'sqlite-current-source-next183',
        ];
        $plan['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM residual matching, and current-source cursor invalidation';

        return $plan;
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
