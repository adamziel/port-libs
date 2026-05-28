<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext162Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNormalizedPatternPlan(
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
        string $currentSource = 'main.wp_options@161',
        string $nextSource = 'main.wp_options@162',
        int $currentSchemaCookie = 161,
        int $nextSchemaCookie = 162,
    ): array {
        $base = SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNext160Plan::wordpressOptionNamePatternPlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $byteReasons = [];
        $semanticReasons = [];
        foreach ($base['invalidationReasons'] as $reason) {
            if (in_array($reason, ['pattern-encoding', 'pattern-bytes', 'escape-bytes'], true)) {
                $byteReasons[] = $reason;
                continue;
            }
            $semanticReasons[] = $reason;
        }

        $sameDecodedPattern = $base['currentPattern'] === $base['nextPattern'];
        $sameDecodedEscape = $base['currentEscape'] === $base['nextEscape'];
        $byteOnlyReprepare = $byteReasons !== [] && $semanticReasons === [] && $sameDecodedPattern && $sameDecodedEscape;

        if ($byteOnlyReprepare) {
            $semanticReasons = [];
        } else {
            if (!$sameDecodedPattern && !in_array('pattern-text', $semanticReasons, true)) {
                $semanticReasons[] = 'pattern-text';
            }
            if (!$sameDecodedEscape && !in_array('escape-text', $semanticReasons, true)) {
                $semanticReasons[] = 'escape-text';
            }
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next162',
            'operator' => 'LIKE',
            'indexCollation' => 'RTRIM',
            'residualCollation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'normalizesPreparedPatternBytes' => true,
            'rawPatternByteChangeIsSemantic' => false,
            'rawEscapeByteChangeIsSemantic' => false,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentPattern' => $base['currentPattern'],
            'nextPattern' => $base['nextPattern'],
            'sameDecodedPattern' => $sameDecodedPattern,
            'currentPatternEncoding' => $base['currentPatternEncoding'],
            'nextPatternEncoding' => $base['nextPatternEncoding'],
            'currentPatternBytesHex' => $base['currentPatternBytesHex'],
            'nextPatternBytesHex' => $base['nextPatternBytesHex'],
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'sameDecodedEscape' => $sameDecodedEscape,
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRtrimRange' => $base['currentRtrimRange'],
            'nextRtrimRange' => $base['nextRtrimRange'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'retainedMatchedRowids' => $base['retainedMatchedRowids'],
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'byteReprepareReasons' => $byteReasons,
            'semanticInvalidationReasons' => $semanticReasons,
            'byteOnlyReprepare' => $byteOnlyReprepare,
            'cursorInvalidated' => $semanticReasons !== [],
            'cursorReusable' => $semanticReasons === [] && $base['currentIndexUsable'] && $base['nextIndexUsable'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-rtrim-collation-range',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-next162',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 pattern decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source diagnostics',
        ];
    }
}
