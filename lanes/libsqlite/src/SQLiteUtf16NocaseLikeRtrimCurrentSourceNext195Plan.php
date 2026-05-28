<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext195Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapedLiteralTailPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_!%!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@194',
        string $nextSource = 'main.wp_options@195',
        int $currentSchemaCookie = 194,
        int $nextSchemaCookie = 195,
    ): array {
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

        $currentFalse = $base['currentRangeFalsePositiveRowids'];
        $nextFalse = $base['nextRangeFalsePositiveRowids'];
        $falseRetained = self::retained($currentFalse, $nextFalse);
        $falseExited = self::exited($currentFalse, $nextFalse);
        $falseEntered = self::entered($currentFalse, $nextFalse);
        $promoted = array_values(array_intersect($falseExited, $base['nextMatchedRowids']));
        $demoted = array_values(array_intersect($base['matchedExitedRowids'], $nextFalse));
        $literalTailReasons = [];
        if (($base['rangeLowerInclusive'] ?? null) !== null && $currentFalse !== $nextFalse) {
            $literalTailReasons[] = 'range-residual-false-positive-rowset';
        }
        if ($promoted !== []) {
            $literalTailReasons[] = 'false-positive-promoted-to-match';
        }
        if ($demoted !== []) {
            $literalTailReasons[] = 'match-demoted-to-false-positive';
        }

        $reasons = array_values(array_unique(array_merge($base['invalidationReasons'], $literalTailReasons)));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next195',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped literal tail */',
            'baseStatus' => $base['status'],
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'prefixCharacters' => $base['likePlan']['prefixCharacters'],
            'prefixIsAscii' => $base['prefixIsAscii'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'usesFullScanFallback' => $base['usesFullScanFallback'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'candidateRetainedRowids' => $base['currentRangeRetainedRowids'],
            'candidateExitedRowids' => $base['currentRangeExitedRowids'],
            'candidateEnteredRowids' => $base['nextRangeEnteredRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentRangeFalsePositiveRowids' => $currentFalse,
            'nextRangeFalsePositiveRowids' => $nextFalse,
            'falsePositiveRetainedRowids' => $falseRetained,
            'falsePositiveExitedRowids' => $falseExited,
            'falsePositiveEnteredRowids' => $falseEntered,
            'falsePositivePromotedRowids' => $promoted,
            'matchedDemotedToFalsePositiveRowids' => $demoted,
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'changedRtrimRowids' => $base['changedRtrimRowids'],
            'changedNocaseKeyRowids' => $base['changedNocaseKeyRowids'],
            'changedBytesRowids' => $base['changedBytesRowids'],
            'literalTailInvalidationReasons' => $literalTailReasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'mustRecheckResidualForRangeCandidates' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escaped-literal-prefix-range',
                'sqlite-rtrim-residual-match',
                'sqlite-current-source-next195',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, escaped LIKE prefix ranges, RTRIM residual matching, and current-source cursor invalidation',
            'non_overlap' => 'covers escaped literal LIKE tails that create prefix-range false positives over UTF-16 RTRIM NOCASE keys; avoids accepted prepared pattern rebind, escape replay, resume-token, Unicode GLOB, and malformed UTF-16 insert guard clusters',
        ];
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }
}
