<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext194Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapedWildcardPrefixPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!%%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@193',
        string $nextSource = 'main.wp_options@194',
        int $currentSchemaCookie = 193,
        int $nextSchemaCookie = 194,
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

        $literalWildcards = self::literalWildcardCharactersInPrefix($pattern, $escape, (string) $base['prefix']);
        $currentLiteralPrefixFalsePositive = self::literalPrefixFalsePositiveRowids($base['currentRtrimTexts'], $base['currentCandidateRowids'], (string) $base['prefix']);
        $nextLiteralPrefixFalsePositive = self::literalPrefixFalsePositiveRowids($base['nextRtrimTexts'], $base['nextCandidateRowids'], (string) $base['prefix']);
        $matchedChanged = array_values(array_unique(array_merge(
            $base['matchedExitedRowids'],
            $base['matchedEnteredRowids'],
            $base['changedRtrimRowids'],
            $currentLiteralPrefixFalsePositive,
            $nextLiteralPrefixFalsePositive,
        )));
        sort($matchedChanged);

        $reasons = $base['invalidationReasons'];
        if ($literalWildcards !== []) {
            $reasons[] = 'escaped-like-wildcard-literal-prefix';
        }
        if ($currentLiteralPrefixFalsePositive !== [] || $nextLiteralPrefixFalsePositive !== []) {
            $reasons[] = 'literal-prefix-residual-recheck';
        }
        if ($matchedChanged !== []) {
            $reasons[] = 'matched-literal-prefix-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next194',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard literal prefix */',
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'escapedWildcardLiteralsInPrefix' => $literalWildcards,
            'escapedPercentIsLiteralPrefixByte' => in_array('%', $literalWildcards, true),
            'escapedUnderscoreIsLiteralPrefixByte' => in_array('_', $literalWildcards, true),
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentRangeFalsePositiveRowids' => $base['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $base['nextRangeFalsePositiveRowids'],
            'currentLiteralPrefixFalsePositiveRowids' => $currentLiteralPrefixFalsePositive,
            'nextLiteralPrefixFalsePositiveRowids' => $nextLiteralPrefixFalsePositive,
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
            'matchedLiteralPrefixChangedRowids' => $matchedChanged,
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $matchedChanged !== [],
            'likeResidualAppliesAfterRtrim' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escaped-wildcard-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next194',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM keys, and LIKE residual matching',
            'non_overlap' => 'next194 adds escaped percent/underscore literal-prefix range diagnostics for UTF-16 NOCASE/RTRIM LIKE current-source cursors; avoids accepted dangling ESCAPE next187, peer-window next189, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /** @return list<string> */
    private static function literalWildcardCharactersInPrefix(string $pattern, ?string $escape, string $prefix): array
    {
        if ($escape === null || $prefix === '') {
            return [];
        }

        $escapeCharacters = self::characters($escape);
        if (count($escapeCharacters) !== 1) {
            return [];
        }

        $escapeCharacter = $escapeCharacters[0];
        $characters = self::characters($pattern);
        $prefixCharacters = self::characters($prefix);
        $literalWildcards = [];
        $prefixPosition = 0;
        $escaped = false;
        foreach ($characters as $character) {
            if ($escaped) {
                if (($prefixCharacters[$prefixPosition] ?? null) === $character) {
                    if ($character === '%' || $character === '_') {
                        $literalWildcards[] = $character;
                    }
                    $prefixPosition++;
                    $escaped = false;
                    continue;
                }
                break;
            }
            if ($character === $escapeCharacter) {
                $escaped = true;
                continue;
            }
            if ($character === '%' || $character === '_') {
                break;
            }
            if (($prefixCharacters[$prefixPosition] ?? null) !== $character) {
                break;
            }
            $prefixPosition++;
        }

        return array_values(array_unique($literalWildcards));
    }

    /** @param array<int,string> $rtrimTexts @param list<int> $candidateRowids @return list<int> */
    private static function literalPrefixFalsePositiveRowids(array $rtrimTexts, array $candidateRowids, string $prefix): array
    {
        $rowids = [];
        $prefixLength = strlen($prefix);
        foreach ($candidateRowids as $rowid) {
            $text = $rtrimTexts[$rowid] ?? null;
            if (!is_string($text)) {
                continue;
            }
            if (self::asciiLower(substr($text, 0, $prefixLength)) !== self::asciiLower($prefix)) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
