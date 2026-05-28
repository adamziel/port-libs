<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext191Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePreparedPatternRebindPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int|string|null $currentEscapeEncoding = null,
        ?string $nextEscapeBytes = null,
        int|string|null $nextEscapeEncoding = null,
        string $currentSource = 'main.wp_options@190',
        string $nextSource = 'main.wp_options@191',
        int $currentSchemaCookie = 190,
        int $nextSchemaCookie = 191,
    ): array {
        $currentPattern = self::decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'pattern');
        $nextPattern = self::decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'pattern');
        $currentEscape = $currentEscapeBytes === null
            ? null
            : self::decodePreparedText($currentEscapeBytes, $currentEscapeEncoding ?? $currentPatternEncoding, 'escape');
        $nextEscape = $nextEscapeBytes === null
            ? null
            : self::decodePreparedText($nextEscapeBytes, $nextEscapeEncoding ?? $nextPatternEncoding, 'escape');

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $currentEscape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $nextEscape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentCandidateRowids = $current['currentCandidateRowids'];
        $nextCandidateRowids = $next['currentCandidateRowids'];
        $currentMatchedRowids = $current['currentMatchedRowids'];
        $nextMatchedRowids = $next['currentMatchedRowids'];
        $sameDecodedPattern = $currentPattern === $nextPattern && $currentEscape === $nextEscape;
        $samePreparedBytes = $currentPatternBytes === $nextPatternBytes
            && $currentPatternEncoding === $nextPatternEncoding
            && $currentEscapeBytes === $nextEscapeBytes
            && $currentEscapeEncoding === $nextEscapeEncoding;
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$sameDecodedPattern) {
            $reasons[] = 'decoded-pattern-or-escape';
        } elseif (!$samePreparedBytes) {
            $reasons[] = 'prepared-pattern-byte-order-refresh';
        }
        foreach ([
            'range-bound' => self::rangeChanged($current, $next),
            'candidate-rowset' => $currentCandidateRowids !== $nextCandidateRowids,
            'matched-rowset' => $currentMatchedRowids !== $nextMatchedRowids,
            'range-false-positive-rowset' => $current['currentRangeFalsePositiveRowids'] !== $next['currentRangeFalsePositiveRowids'],
        ] as $reason => $changed) {
            if ($changed) {
                $reasons[] = $reason;
            }
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next191',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentEscapeEncoding' => $currentEscapeBytes === null ? null : self::encodingName($currentEscapeEncoding ?? $currentPatternEncoding),
            'nextEscapeEncoding' => $nextEscapeBytes === null ? null : self::encodingName($nextEscapeEncoding ?? $nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => $currentEscapeBytes === null ? null : bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => $nextEscapeBytes === null ? null : bin2hex($nextEscapeBytes),
            'sameDecodedPatternAndEscape' => $sameDecodedPattern,
            'samePreparedPatternBytes' => $samePreparedBytes,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $current['prefix'],
            'nextPrefix' => $next['prefix'],
            'currentRangeLowerInclusive' => $current['rangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['rangeLowerInclusive'],
            'currentRangeUpperBound' => $current['rangeUpperBound'],
            'nextRangeUpperBound' => $next['rangeUpperBound'],
            'currentIndexUsable' => $current['indexUsable'],
            'nextIndexUsable' => $next['indexUsable'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'candidateRetainedRowids' => self::retained($currentCandidateRowids, $nextCandidateRowids),
            'candidateExitedRowids' => self::exited($currentCandidateRowids, $nextCandidateRowids),
            'candidateEnteredRowids' => self::entered($currentCandidateRowids, $nextCandidateRowids),
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'matchedRetainedRowids' => self::retained($currentMatchedRowids, $nextMatchedRowids),
            'matchedExitedRowids' => self::exited($currentMatchedRowids, $nextMatchedRowids),
            'matchedEnteredRowids' => self::entered($currentMatchedRowids, $nextMatchedRowids),
            'currentRangeFalsePositiveRowids' => $current['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $next['currentRangeFalsePositiveRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'currentMatchedTexts' => $current['currentMatchedTexts'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'mustReprepareForPatternChange' => !$sameDecodedPattern,
            'canReuseResidualForByteOrderOnlyRebind' => $sameDecodedPattern,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-rebind',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next191',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE pattern normalization, ASCII NOCASE prefix ranges, RTRIM keys, and current-source diagnostics',
            'non_overlap' => 'adds prepared UTF-16 pattern rebind diagnostics where decoded LIKE pattern or escape changes between current and next sources; avoids accepted stable byte-order normalization, resume-token, dangling-escape, NUL, case_sensitive_like, Unicode GLOB, and malformed insert guard clusters',
        ];
    }

    private static function decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next191 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $next */
    private static function rangeChanged(array $current, array $next): bool
    {
        return ($current['rangeLowerInclusive'] ?? null) !== ($next['rangeLowerInclusive'] ?? null)
            || ($current['rangeUpperBound'] ?? null) !== ($next['rangeUpperBound'] ?? null);
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

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next191 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int|string $encoding): string
    {
        return match (self::encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }
}
