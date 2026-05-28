<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameDanglingEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@186',
        string $nextSource = 'main.wp_options@187',
        int $currentSchemaCookie = 186,
        int $nextSchemaCookie = 187,
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

        $danglingEscape = self::hasDanglingEscape($pattern, $escape);
        $currentResidualMisses = $danglingEscape ? $base['currentCandidateRowids'] : $base['currentRangeFalsePositiveRowids'];
        $nextResidualMisses = $danglingEscape ? $base['nextCandidateRowids'] : $base['nextRangeFalsePositiveRowids'];
        $reasons = $base['invalidationReasons'];
        if ($danglingEscape) {
            $reasons[] = 'dangling-like-escape-residual';
        }
        if ($currentResidualMisses !== [] || $nextResidualMisses !== []) {
            $reasons[] = 'residual-recheck-required';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next187',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'patternEndsWithEscape' => $danglingEscape,
            'sqliteDanglingEscapeMatchesNoRows' => $danglingEscape,
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentDanglingEscapeResidualMissRowids' => $currentResidualMisses,
            'nextDanglingEscapeResidualMissRowids' => $nextResidualMisses,
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'rangeRetainedRowids' => $base['currentRangeRetainedRowids'],
            'rangeExitedRowids' => $base['currentRangeExitedRowids'],
            'rangeEnteredRowids' => $base['nextRangeEnteredRowids'],
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $currentResidualMisses !== [] || $nextResidualMisses !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-dangling-escape-residual',
                'sqlite-nocase-prefix-range-recheck',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next187',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM keys, and SQLite LIKE residual matching',
            'non_overlap' => 'adds dangling ESCAPE residual recheck behavior for UTF-16 NOCASE/RTRIM LIKE current-source prefix cursors; avoids accepted next183 prefix reuse, next184 escaped peer replay, prepared-pattern byte normalization, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function hasDanglingEscape(string $pattern, ?string $escape): bool
    {
        $escapeCharacters = $escape === null ? [] : self::characters($escape);
        if (count($escapeCharacters) !== 1) {
            return false;
        }

        $patternCharacters = self::characters($pattern);
        if ($patternCharacters === []) {
            return false;
        }

        $escapeCharacter = $escapeCharacters[0];
        $escaped = false;
        foreach ($patternCharacters as $character) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === $escapeCharacter) {
                $escaped = true;
            }
        }

        return $escaped;
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters)) {
            return array_values($characters);
        }

        return str_split($value);
    }
}
