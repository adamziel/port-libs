<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext190Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameAsciiSpaceTrimBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin%',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@189',
        string $nextSource = 'main.wp_options@190',
        int $currentSchemaCookie = 189,
        int $nextSchemaCookie = 190,
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

        $currentWhitespace = self::classifyWhitespaceSuffixes($base['currentTexts'], $base['currentRtrimTexts']);
        $nextWhitespace = self::classifyWhitespaceSuffixes($base['nextTexts'], $base['nextRtrimTexts']);
        $changedWhitespace = self::changedWhitespaceClasses($currentWhitespace, $nextWhitespace);
        $asciiTrimOnlyChanged = self::asciiTrimOnlyChangedRowids($base, $changedWhitespace);
        $rangeRetainedChanged = array_values(array_intersect($base['currentRangeRetainedRowids'], $asciiTrimOnlyChanged));
        $matchedRetainedChanged = array_values(array_intersect($base['matchedRetainedRowids'], $asciiTrimOnlyChanged));

        $reasons = $base['invalidationReasons'];
        if ($changedWhitespace !== []) {
            $reasons[] = 'trailing-whitespace-class';
        }
        if ($rangeRetainedChanged !== []) {
            $reasons[] = 'retained-prefix-rtrim-key';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next190',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ?',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentTrailingWhitespace' => $currentWhitespace,
            'nextTrailingWhitespace' => $nextWhitespace,
            'changedTrailingWhitespaceClassRowids' => $changedWhitespace,
            'asciiSpaceTrimBoundaryChangedRowids' => $asciiTrimOnlyChanged,
            'retainedRangeRtrimKeyChangedRowids' => $rangeRetainedChanged,
            'retainedMatchRtrimKeyChangedRowids' => $matchedRetainedChanged,
            'rangeRetainedRowids' => $base['currentRangeRetainedRowids'],
            'rangeExitedRowids' => $base['currentRangeExitedRowids'],
            'rangeEnteredRowids' => $base['nextRangeEnteredRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $rangeRetainedChanged !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nonBreakingSpaceNotTrimmed' => true,
            'tabNotTrimmed' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-ascii-space-boundary',
                'sqlite-current-source-next190',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and current-source invalidation diagnostics',
            'non_overlap' => 'adds ASCII-space-only RTRIM boundary diagnostics for retained UTF-16 NOCASE LIKE prefix cursor rows; avoids accepted next187 dangling ESCAPE residual checks, next183 prefix range reuse, next184 escaped peer replay, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /** @param array<int,string> $texts @param array<int,string> $rtrimTexts @return array<int,array<string,mixed>> */
    private static function classifyWhitespaceSuffixes(array $texts, array $rtrimTexts): array
    {
        $classes = [];
        foreach ($texts as $rowid => $text) {
            $suffix = substr($text, strlen($rtrimTexts[$rowid] ?? $text));
            $classes[(int) $rowid] = [
                'suffix' => $suffix,
                'suffixHex' => bin2hex($suffix),
                'asciiSpaceCount' => substr_count($suffix, ' '),
                'hasTabSuffix' => str_ends_with($text, "\t"),
                'hasNewlineSuffix' => str_ends_with($text, "\n"),
                'hasNonBreakingSpaceSuffix' => str_ends_with($text, "\u{00a0}"),
                'trimmedByRtrim' => $suffix !== '',
            ];
        }
        ksort($classes);

        return $classes;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function changedWhitespaceClasses(array $current, array $next): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid]['suffixHex'] ?? null) !== ($next[$rowid]['suffixHex'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param array<string,mixed> $base @param list<int> $changedWhitespace @return list<int> */
    private static function asciiTrimOnlyChangedRowids(array $base, array $changedWhitespace): array
    {
        $changed = array_values(array_intersect(
            $changedWhitespace,
            $base['changedRtrimRowids'],
            $base['changedNocaseKeyRowids'],
        ));
        sort($changed);

        return $changed;
    }
}
