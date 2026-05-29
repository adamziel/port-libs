<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNext160Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePatternPlan(
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
        string $currentSource = 'main.wp_options@159',
        string $nextSource = 'main.wp_options@160',
        int $currentSchemaCookie = 159,
        int $nextSchemaCookie = 160,
    ): array {
        $currentPattern = self::decodeSqlText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::decodeSqlText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentEscape = self::decodeEscape($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextEscape = self::decodeEscape($nextEscapeBytes, $nextEscapeEncoding, 'next escape');

        $currentPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $currentEscape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextPlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan(
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
        if ($currentPatternEncoding !== $nextPatternEncoding) {
            $reasons[] = 'pattern-encoding';
        }
        if ($currentPatternBytes !== $nextPatternBytes) {
            $reasons[] = 'pattern-bytes';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'escape-text';
        }
        if (($currentEscapeBytes ?? '') !== ($nextEscapeBytes ?? '')) {
            $reasons[] = 'escape-bytes';
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
            'status' => 'utf16-nocase-like-rtrim-pattern-current-source-next160',
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
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentEscapeBytesHex' => $currentEscapeBytes === null ? null : bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => $nextEscapeBytes === null ? null : bin2hex($nextEscapeBytes),
            'currentPrefix' => $currentPlan['prefix'],
            'nextPrefix' => $nextPlan['prefix'],
            'currentRtrimRange' => $currentPlan['rtrimRange'],
            'nextRtrimRange' => $nextPlan['rtrimRange'],
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
            'currentMalformedRowids' => $currentPlan['currentMalformedRowids'],
            'nextMalformedRowids' => $nextPlan['currentMalformedRowids'],
            'currentErrors' => $currentPlan['currentErrors'],
            'nextErrors' => $nextPlan['currentErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $currentPlan['indexUsable'] && $nextPlan['indexUsable'],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-rtrim-collation-range',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-next160',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source cursor diagnostics',
        ];
    }

    private static function decodeSqlText(string $bytes, int $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next160 ' . $label . ' is malformed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private static function decodeEscape(?string $bytes, int $encoding, string $label): ?string
    {
        if ($bytes === null) {
            return null;
        }
        $escape = self::decodeSqlText($bytes, $encoding, $label);
        $characters = preg_split('//u', $escape, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($characters) !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next160 ' . $label . ' must decode to exactly one character');
        }

        return $escape;
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
