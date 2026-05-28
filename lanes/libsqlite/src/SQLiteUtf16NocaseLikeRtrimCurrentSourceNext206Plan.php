<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext206Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePreparedBomPatternPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@205',
        string $nextSource = 'main.wp_options@206',
        int $currentSchemaCookie = 205,
        int $nextSchemaCookie = 206,
    ): array {
        $currentDecoded = self::decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextDecoded = self::decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentPattern = self::stripLeadingBom($currentDecoded);
        $nextPattern = self::stripLeadingBom($nextDecoded);
        $currentHadBom = $currentDecoded !== $currentPattern;
        $nextHadBom = $nextDecoded !== $nextPattern;

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $escape,
            $currentPattern,
            $escape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $escape,
            $nextPattern,
            $escape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );
        $nextWithoutBomStrip = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextDecoded,
            $escape,
            $nextDecoded,
            $escape,
            $nextSource . '#raw-bom',
            $nextSource . '#raw-bom',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentMatched = $current['currentMatchedRowids'];
        $nextMatched = $next['currentMatchedRowids'];
        $nextRawMatched = $nextWithoutBomStrip['currentMatchedRowids'];
        $bomRescued = array_values(array_diff($nextMatched, $nextRawMatched));
        sort($bomRescued);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentHadBom || $nextHadBom) {
            $reasons[] = 'prepared-pattern-bom';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'decoded-pattern';
        }
        if ($current['currentCandidateRowids'] !== $next['currentCandidateRowids']) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($bomRescued !== []) {
            $reasons[] = 'bom-prefix-residual-rowset';
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next206',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 BOM pattern */',
            'currentPatternDecoded' => $currentDecoded,
            'nextPatternDecoded' => $nextDecoded,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentPatternHadBom' => $currentHadBom,
            'nextPatternHadBom' => $nextHadBom,
            'currentPatternEncoding' => self::encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $current['currentPrefix'],
            'nextPrefix' => $next['currentPrefix'],
            'rawBomPrefix' => $nextWithoutBomStrip['currentPrefix'],
            'currentRangeLowerInclusive' => $current['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['currentRangeLowerInclusive'],
            'rawBomRangeLowerInclusive' => $nextWithoutBomStrip['currentRangeLowerInclusive'],
            'currentRangeUpperBound' => $current['currentRangeUpperBound'],
            'nextRangeUpperBound' => $next['currentRangeUpperBound'],
            'rawBomRangeUpperBound' => $nextWithoutBomStrip['currentRangeUpperBound'],
            'currentIndexUsable' => $current['currentIndexUsable'],
            'nextIndexUsable' => $next['currentIndexUsable'],
            'rawBomIndexUsable' => $nextWithoutBomStrip['currentIndexUsable'],
            'currentCandidateRowids' => $current['currentCandidateRowids'],
            'nextCandidateRowids' => $next['currentCandidateRowids'],
            'rawBomCandidateRowids' => $nextWithoutBomStrip['currentCandidateRowids'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'rawBomMatchedRowids' => $nextRawMatched,
            'bomRescuedMatchedRowids' => $bomRescued,
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentFalsePositiveRowids' => $current['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $next['currentFalsePositiveRowids'],
            'rawBomFalsePositiveRowids' => $nextWithoutBomStrip['currentFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $current['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $next['currentExcludedDecodedRowids'],
            'rawBomExcludedDecodedRowids' => $nextWithoutBomStrip['currentExcludedDecodedRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForPreparedBom' => $currentHadBom || $nextHadBom || $currentPattern !== $nextPattern,
            'bomStrippedBeforePrefixPlanning' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-bom-normalization',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next206',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE RHS normalization, ASCII NOCASE prefix ranges, RTRIM keys, and current-source cursor diagnostics',
            'non_overlap' => 'next206 covers UTF-16 prepared LIKE pattern BOM normalization before NOCASE/RTRIM prefix planning; avoids accepted escape rebind next200, no-prefix next203, escaped literal next194/195, dangling ESCAPE next187, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next206 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function stripLeadingBom(string $value): string
    {
        return str_starts_with($value, "\xef\xbb\xbf") ? substr($value, 3) : $value;
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next206 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
