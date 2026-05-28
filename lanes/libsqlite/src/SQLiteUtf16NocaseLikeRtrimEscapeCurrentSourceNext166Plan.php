<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        string $currentEscapeBytes,
        int $currentEscapeEncoding,
        string $nextEscapeBytes,
        int $nextEscapeEncoding,
        string $currentSource = 'main.wp_options@165',
        string $nextSource = 'main.wp_options@166',
        int $currentSchemaCookie = 165,
        int $nextSchemaCookie = 166,
    ): array {
        $currentEscape = self::decodeSqlText($currentEscapeBytes, $currentEscapeEncoding, 'current rtrim(escape)');
        $nextEscape = self::decodeSqlText($nextEscapeBytes, $nextEscapeEncoding, 'next rtrim(escape)');
        $currentTrimmedEscape = rtrim($currentEscape, ' ');
        $nextTrimmedEscape = rtrim($nextEscape, ' ');
        self::assertSingleCharacterEscape($currentTrimmedEscape, 'current');
        self::assertSingleCharacterEscape($nextTrimmedEscape, 'next');

        $base = SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext163Plan::wordpressOptionNameRtrimPatternPlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentTrimmedEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $nextBase = SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext163Plan::wordpressOptionNameRtrimPatternPlan(
            $nextRows,
            $nextRows,
            $nextPatternBytes,
            $nextPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $nextTrimmedEscape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $semanticReasons = $base['invalidationReasons'];
        if ($currentTrimmedEscape !== $nextTrimmedEscape && !in_array('rtrim-escape-text', $semanticReasons, true)) {
            $semanticReasons[] = 'rtrim-escape-text';
        }
        if ($base['nextCandidateRowids'] !== $nextBase['nextCandidateRowids'] && !in_array('escape-candidate-rowset', $semanticReasons, true)) {
            $semanticReasons[] = 'escape-candidate-rowset';
        }
        if ($base['nextMatchedRowids'] !== $nextBase['nextMatchedRowids'] && !in_array('escape-matched-rowset', $semanticReasons, true)) {
            $semanticReasons[] = 'escape-matched-rowset';
        }

        $byteReasons = [];
        if ($currentEscapeEncoding !== $nextEscapeEncoding) {
            $byteReasons[] = 'escape-encoding';
        }
        if ($currentEscapeBytes !== $nextEscapeBytes) {
            $byteReasons[] = 'escape-bytes';
        }
        $currentTrimmedEscapeBytes = SQLiteEncodingCollationSourceCursor::encodeText($currentTrimmedEscape, $currentEscapeEncoding);
        $nextTrimmedEscapeBytes = SQLiteEncodingCollationSourceCursor::encodeText($nextTrimmedEscape, $nextEscapeEncoding);
        if ($currentTrimmedEscapeBytes !== $nextTrimmedEscapeBytes) {
            $byteReasons[] = 'rtrim-escape-bytes';
        }

        $byteOnlyReprepare = $byteReasons !== []
            && $semanticReasons === []
            && $currentTrimmedEscape === $nextTrimmedEscape;

        return [
            'status' => 'utf16-nocase-like-rtrim-escape-current-source-next166',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE rtrim(?) ESCAPE rtrim(?)',
            'caseSensitiveLike' => false,
            'collation' => 'NOCASE',
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentPattern' => $base['currentPattern'],
            'nextPattern' => $base['nextPattern'],
            'currentTrimmedPattern' => $base['currentTrimmedPattern'],
            'nextTrimmedPattern' => $base['nextTrimmedPattern'],
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentTrimmedEscape' => $currentTrimmedEscape,
            'nextTrimmedEscape' => $nextTrimmedEscape,
            'currentEscapeEncoding' => self::encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::encodingName($nextEscapeEncoding),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'currentTrimmedEscapeBytesHex' => bin2hex($currentTrimmedEscapeBytes),
            'nextTrimmedEscapeBytesHex' => bin2hex($nextTrimmedEscapeBytes),
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefixWithCurrentEscape' => $base['nextPrefix'],
            'nextPrefixWithNextEscape' => $nextBase['nextPrefix'],
            'currentRange' => $base['currentRange'],
            'nextRangeWithCurrentEscape' => $base['nextRange'],
            'nextRangeWithNextEscape' => $nextBase['nextRange'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsableWithCurrentEscape' => $base['nextIndexUsable'],
            'nextIndexUsableWithNextEscape' => $nextBase['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowidsWithCurrentEscape' => $base['nextCandidateRowids'],
            'nextCandidateRowidsWithNextEscape' => $nextBase['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowidsWithCurrentEscape' => $base['nextMatchedRowids'],
            'nextMatchedRowidsWithNextEscape' => $nextBase['nextMatchedRowids'],
            'retainedMatchedRowids' => $base['retainedMatchedRowids'],
            'enteredMatchedRowidsWithCurrentEscape' => $base['enteredMatchedRowids'],
            'enteredMatchedRowidsWithNextEscape' => array_values(array_diff($nextBase['nextMatchedRowids'], $base['currentMatchedRowids'])),
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowidsWithCurrentEscape' => $base['nextFalsePositiveRowids'],
            'nextFalsePositiveRowidsWithNextEscape' => $nextBase['nextFalsePositiveRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'escapeByteReasons' => $byteReasons,
            'sameTrimmedEscape' => $currentTrimmedEscape === $nextTrimmedEscape,
            'semanticInvalidationReasons' => $byteOnlyReprepare ? [] : $semanticReasons,
            'byteOnlyReprepare' => $byteOnlyReprepare,
            'cursorInvalidated' => !$byteOnlyReprepare && $semanticReasons !== [],
            'cursorReusable' => $byteOnlyReprepare || ($semanticReasons === [] && $base['currentIndexUsable'] && $nextBase['nextIndexUsable']),
            'rtrimEscapeTrimsOnlyAsciiSpace' => true,
            'escapeMustBeSingleCharacterAfterRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-utf16-escape-decode',
                'sqlite-rtrim-rhs-expression',
                'sqlite-rtrim-escape-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-next166',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RHS RTRIM pattern handling, ESCAPE RTRIM normalization, NOCASE LIKE range planning, and current-source diagnostics',
        ];
    }

    private static function decodeSqlText(string $bytes, int $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next166 ' . $label . ' is malformed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private static function assertSingleCharacterEscape(string $escape, string $label): void
    {
        $characters = preg_split('//u', $escape, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || count($characters) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next166 {$label} ESCAPE rtrim() must yield exactly one character");
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
