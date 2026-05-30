<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
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
        string $currentSource = 'main.app_settings@158',
        string $nextSource = 'main.app_settings@159',
        int $currentSchemaCookie = 158,
        int $nextSchemaCookie = 159,
    ): array {
        $currentPattern = self::decodeText($currentPatternBytes, $currentPatternEncoding, 'pattern');
        $nextPattern = self::decodeText($nextPatternBytes, $nextPatternEncoding, 'pattern');
        $currentEscape = self::decodeEscape($currentEscapeBytes, $currentEscapeEncoding ?? $currentPatternEncoding);
        $nextEscape = self::decodeEscape($nextEscapeBytes, $nextEscapeEncoding ?? $nextPatternEncoding);

        $currentPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNoCasePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $currentEscape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNoCasePlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $nextEscape,
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
        if (self::encodingName(self::normalizeEncoding($currentPatternEncoding)) !== self::encodingName(self::normalizeEncoding($nextPatternEncoding))) {
            $reasons[] = 'pattern-encoding';
        }
        if ($currentPatternBytes !== $nextPatternBytes) {
            $reasons[] = 'pattern-bytes';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'escape-text';
        }
        if (($currentEscapeBytes === null) !== ($nextEscapeBytes === null)) {
            $reasons[] = 'escape-presence';
        } elseif ($currentEscapeBytes !== null && $nextEscapeBytes !== null) {
            if (self::encodingName(self::normalizeEncoding($currentEscapeEncoding ?? $currentPatternEncoding)) !== self::encodingName(self::normalizeEncoding($nextEscapeEncoding ?? $nextPatternEncoding))) {
                $reasons[] = 'escape-encoding';
            }
            if ($currentEscapeBytes !== $nextEscapeBytes) {
                $reasons[] = 'escape-bytes';
            }
        }
        if ($currentPlan['currentCandidateRowids'] !== $nextPlan['currentCandidateRowids']) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentPlan['currentMatchedRowids'] !== $nextPlan['currentMatchedRowids']) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentPlan['currentFalsePositiveRowids'] !== $nextPlan['currentFalsePositiveRowids']) {
            $reasons[] = 'false-positive-rowset';
        }
        if ($currentPlan['currentMalformedRowids'] !== [] || $nextPlan['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-row-text';
        }

        return [
            'status' => 'utf16-pattern-nocase-like-rtrim-current-source-nextoneFiveNine',
            'operator' => 'LIKE',
            'indexCollation' => 'RTRIM',
            'residualCollation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentPatternEncoding' => self::encodingName(self::normalizeEncoding($currentPatternEncoding)),
            'nextPatternEncoding' => self::encodingName(self::normalizeEncoding($nextPatternEncoding)),
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentEscapeBytesHex' => $currentEscapeBytes === null ? null : bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => $nextEscapeBytes === null ? null : bin2hex($nextEscapeBytes),
            'currentEscapeEncoding' => $currentEscapeBytes === null ? null : self::encodingName(self::normalizeEncoding($currentEscapeEncoding ?? $currentPatternEncoding)),
            'nextEscapeEncoding' => $nextEscapeBytes === null ? null : self::encodingName(self::normalizeEncoding($nextEscapeEncoding ?? $nextPatternEncoding)),
            'currentPrefix' => $currentPlan['prefix'],
            'nextPrefix' => $nextPlan['prefix'],
            'currentRange' => $currentPlan['rtrimRange'],
            'nextRange' => $nextPlan['rtrimRange'],
            'currentIndexUsable' => $currentPlan['indexUsable'],
            'nextIndexUsable' => $nextPlan['indexUsable'],
            'currentHasDanglingEscape' => $currentPlan['hasDanglingEscape'],
            'nextHasDanglingEscape' => $nextPlan['hasDanglingEscape'],
            'currentOrderRowids' => $currentPlan['currentOrderRowids'],
            'nextOrderRowids' => $nextPlan['currentOrderRowids'],
            'currentCandidateRowids' => $currentPlan['currentCandidateRowids'],
            'nextCandidateRowids' => $nextPlan['currentCandidateRowids'],
            'currentMatchedRowids' => $currentPlan['currentMatchedRowids'],
            'nextMatchedRowids' => $nextPlan['currentMatchedRowids'],
            'currentFalsePositiveRowids' => $currentPlan['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $nextPlan['currentFalsePositiveRowids'],
            'retainedMatchedRowids' => array_values(array_intersect($currentPlan['currentMatchedRowids'], $nextPlan['currentMatchedRowids'])),
            'enteredMatchedRowids' => array_values(array_diff($nextPlan['currentMatchedRowids'], $currentPlan['currentMatchedRowids'])),
            'exitedMatchedRowids' => array_values(array_diff($currentPlan['currentMatchedRowids'], $nextPlan['currentMatchedRowids'])),
            'currentTexts' => $currentPlan['currentTexts'],
            'nextTexts' => $nextPlan['currentTexts'],
            'currentRtrimKeys' => $currentPlan['currentRtrimKeys'],
            'nextRtrimKeys' => $nextPlan['currentRtrimKeys'],
            'currentNoCaseKeys' => $currentPlan['currentNoCaseKeys'],
            'nextNoCaseKeys' => $nextPlan['currentNoCaseKeys'],
            'currentResidualMatches' => $currentPlan['currentResidualMatches'],
            'nextResidualMatches' => $nextPlan['currentResidualMatches'],
            'currentMalformedRowids' => $currentPlan['currentMalformedRowids'],
            'nextMalformedRowids' => $nextPlan['currentMalformedRowids'],
            'currentErrors' => $currentPlan['currentErrors'],
            'nextErrors' => $nextPlan['currentErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $currentPlan['cursorReusable'] && $nextPlan['cursorReusable'],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-utf16-text-decode',
                'sqlite-rtrim-collation-range',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-nextoneFiveNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 pattern/text decode, RTRIM range keys, and ASCII NOCASE LIKE residual matching',
        ];
    }

    private static function decodeEscape(?string $bytes, int|string|null $encoding): ?string
    {
        if ($bytes === null) {
            return null;
        }
        if ($encoding === null) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveNine escape encoding is required');
        }
        $escape = self::decodeText($bytes, $encoding, 'escape');
        if (self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite LIKE ESCAPE expression must be a single character');
        }

        return $escape;
    }

    private static function decodeText(string $bytes, int|string $encoding, string $context): string
    {
        $text = SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::normalizeEncoding($encoding));
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveNine {$context} decoded to malformed UTF-8");
        }

        return $text;
    }

    private static function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveNine encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE', 'UTF-16' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveNine encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveNine encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }
}
