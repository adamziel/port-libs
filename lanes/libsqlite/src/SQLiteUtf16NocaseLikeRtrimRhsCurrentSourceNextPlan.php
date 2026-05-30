<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowNameRtrimPatternPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $escape = '\\',
        string $currentSource = 'main.wp_options@162',
        string $nextSource = 'main.wp_options@163',
        int $currentSchemaCookie = 162,
        int $nextSchemaCookie = 163,
    ): array {
        $currentPattern = self::decodeSqlText($currentPatternBytes, $currentPatternEncoding, 'current rtrim(pattern)');
        $nextPattern = self::decodeSqlText($nextPatternBytes, $nextPatternEncoding, 'next rtrim(pattern)');
        $currentTrimmedPattern = rtrim($currentPattern, ' ');
        $nextTrimmedPattern = rtrim($nextPattern, ' ');
        $currentTrimmedBytes = SQLiteEncodingCollationSourceCursor::encodeText($currentTrimmedPattern, $currentPatternEncoding);
        $nextTrimmedBytes = SQLiteEncodingCollationSourceCursor::encodeText($nextTrimmedPattern, $nextPatternEncoding);

        $currentPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceDeltaPlan(
            $currentRows,
            $currentRows,
            $currentTrimmedPattern,
            $escape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceDeltaPlan(
            $nextRows,
            $nextRows,
            $nextTrimmedPattern,
            $escape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'pattern-text';
        }
        if ($currentTrimmedPattern !== $nextTrimmedPattern) {
            $reasons[] = 'rtrim-pattern-text';
        }
        if ($currentPatternEncoding !== $nextPatternEncoding) {
            $reasons[] = 'pattern-encoding';
        }
        if ($currentPatternBytes !== $nextPatternBytes) {
            $reasons[] = 'pattern-bytes';
        }
        if ($currentTrimmedBytes !== $nextTrimmedBytes) {
            $reasons[] = 'rtrim-pattern-bytes';
        }
        if ($currentPlan['currentCandidateRowids'] !== $nextPlan['currentCandidateRowids']) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentPlan['currentMatchedRowids'] !== $nextPlan['currentMatchedRowids']) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentPlan['currentFalsePositiveRowids'] !== $nextPlan['currentFalsePositiveRowids']) {
            $reasons[] = 'rtrim-false-positive-rowset';
        }
        if ($currentPlan['currentMalformedRowids'] !== [] || $nextPlan['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-rhs-current-source-nextoneSixThree',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE rtrim(?)',
            'caseSensitiveLike' => false,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentTrimmedPattern' => $currentTrimmedPattern,
            'nextTrimmedPattern' => $nextTrimmedPattern,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentTrimmedPatternBytesHex' => bin2hex($currentTrimmedBytes),
            'nextTrimmedPatternBytesHex' => bin2hex($nextTrimmedBytes),
            'escape' => $escape,
            'currentPrefix' => $currentPlan['prefix'],
            'nextPrefix' => $nextPlan['prefix'],
            'currentRange' => $currentPlan['range'],
            'nextRange' => $nextPlan['range'],
            'currentIndexUsable' => $currentPlan['indexUsable'],
            'nextIndexUsable' => $nextPlan['indexUsable'],
            'currentCandidateRowids' => $currentPlan['currentCandidateRowids'],
            'nextCandidateRowids' => $nextPlan['currentCandidateRowids'],
            'currentMatchedRowids' => $currentPlan['currentMatchedRowids'],
            'nextMatchedRowids' => $nextPlan['currentMatchedRowids'],
            'currentFalsePositiveRowids' => $currentPlan['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $nextPlan['currentFalsePositiveRowids'],
            'retainedMatchedRowids' => array_values(array_intersect($currentPlan['currentMatchedRowids'], $nextPlan['currentMatchedRowids'])),
            'enteredMatchedRowids' => array_values(array_diff($nextPlan['currentMatchedRowids'], $currentPlan['currentMatchedRowids'])),
            'exitedMatchedRowids' => array_values(array_diff($currentPlan['currentMatchedRowids'], $nextPlan['currentMatchedRowids'])),
            'currentRtrimTexts' => $currentPlan['currentRtrimTexts'],
            'nextRtrimTexts' => $nextPlan['currentRtrimTexts'],
            'currentNocaseKeys' => $currentPlan['currentNocaseKeys'],
            'nextNocaseKeys' => $nextPlan['currentNocaseKeys'],
            'currentResidualMatches' => $currentPlan['currentResidualMatches'],
            'nextResidualMatches' => $nextPlan['currentResidualMatches'],
            'currentMalformedRowids' => $currentPlan['currentMalformedRowids'],
            'nextMalformedRowids' => $nextPlan['currentMalformedRowids'],
            'currentErrors' => $currentPlan['currentErrors'],
            'nextErrors' => $nextPlan['currentErrors'],
            'rtrimPatternTrimsOnlyAsciiSpace' => true,
            'rtrimColumnTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $currentPlan['indexUsable'] && $nextPlan['indexUsable'],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-rtrim-rhs-expression',
                'sqlite-rtrim-expression-index-key',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-nextoneSixThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RHS RTRIM expression trimming, NOCASE LIKE range planning, and current-source cursor diagnostics',
        ];
    }

    private static function decodeSqlText(string $bytes, int $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixThree ' . $label . ' is malformed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => 'unknown-' . $encoding,
        };
    }
}
