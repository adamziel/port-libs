<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext208Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePreparedEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "\xfe\xff\x00~",
        int|string $nextEscapeEncoding = 'UTF-16BE',
        string $currentSource = 'main.wp_options@207',
        string $nextSource = 'main.wp_options@208',
        int $currentSchemaCookie = 207,
        int $nextSchemaCookie = 208,
    ): array {
        $currentDecoded = self::decodePreparedEscape($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextDecoded = self::decodePreparedEscape($nextEscapeBytes, $nextEscapeEncoding, 'next escape');
        $currentEscape = self::stripLeadingBom($currentDecoded);
        $nextEscape = self::stripLeadingBom($nextDecoded);
        self::assertSingleCharacter($currentEscape, 'current escape');
        self::assertSingleCharacter($nextEscape, 'next escape');

        $currentHadBom = $currentDecoded !== $currentEscape;
        $nextHadBom = $nextDecoded !== $nextEscape;
        $currentPattern = self::rewritePatternEscape($pattern, '!', $currentEscape);
        $nextPattern = self::rewritePatternEscape($pattern, '!', $nextEscape);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $currentEscape,
            $nextPattern,
            $nextEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $rawNext = null;
        $rawNextError = null;
        try {
            $rawNext = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan::wordpressOptionNameEscapeRebindPlan(
                $nextRows,
                $nextRows,
                $nextPattern,
                $nextDecoded,
                $nextPattern,
                $nextDecoded,
                $nextSource . '#raw-escape-bom',
                $nextSource . '#raw-escape-bom',
                $nextSchemaCookie,
                $nextSchemaCookie,
            );
        } catch (\InvalidArgumentException $exception) {
            $rawNextError = $exception->getMessage();
        }

        $reasons = $base['invalidationReasons'];
        if ($currentHadBom || $nextHadBom) {
            $reasons[] = 'prepared-escape-bom';
        }
        if ($currentEscapeBytes !== $nextEscapeBytes || self::encodingName($currentEscapeEncoding) !== self::encodingName($nextEscapeEncoding)) {
            $reasons[] = 'prepared-escape-bytes';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next208',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 escape */',
            'patternTemplate' => $pattern,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscapeDecoded' => $currentDecoded,
            'nextEscapeDecoded' => $nextDecoded,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentEscapeHadBom' => $currentHadBom,
            'nextEscapeHadBom' => $nextHadBom,
            'currentEscapeEncoding' => self::encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::encodingName($nextEscapeEncoding),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'preparedEscapeChanged' => $currentEscape !== $nextEscape,
            'preparedEscapeBytesChanged' => $currentEscapeBytes !== $nextEscapeBytes,
            'preparedEscapeBomStrippedBeforeValidation' => $currentHadBom || $nextHadBom,
            'rawBomEscapeRejected' => $rawNext === null,
            'rawBomEscapeError' => $rawNextError,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedWithCurrentEscapeRowids' => $base['nextMatchedWithCurrentEscapeRowids'],
            'currentMatchedWithNextEscapeRowids' => $base['currentMatchedWithNextEscapeRowids'],
            'escapeResidualFlipRowids' => $base['escapeResidualFlipRowids'],
            'currentEscapeResidualFlipRowids' => $base['currentEscapeResidualFlipRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'escapeChanged' => $base['escapeChanged'],
            'prefixChangedByEscape' => $base['prefixChangedByEscape'],
            'rangeChangedByEscape' => $base['rangeChangedByEscape'],
            'residualChangedByEscape' => $base['residualChangedByEscape'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForPreparedEscape' => $currentEscape !== $nextEscape || $currentHadBom || $nextHadBom,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-escape-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next208',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE byte normalization, LIKE ESCAPE prefix planning, RTRIM keys, and current-source cursor diagnostics',
            'non_overlap' => 'next208 covers prepared UTF-16 ESCAPE parameter decoding and BOM stripping before NOCASE/RTRIM LIKE range planning; avoids accepted prepared-pattern BOM next206, escape rebind next200, no-prefix next203, escaped literal next194/195, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedEscape(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next208 prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function stripLeadingBom(string $value): string
    {
        return str_starts_with($value, "\xef\xbb\xbf") ? substr($value, 3) : $value;
    }

    private static function assertSingleCharacter(string $value, string $label): void
    {
        if ($value === '' || preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next208 prepared {$label} must be one UTF-8 character");
        }
        if (count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM next208 prepared {$label} must be one UTF-8 character");
        }
    }

    private static function rewritePatternEscape(string $pattern, string $from, string $to): string
    {
        return $from === $to ? $pattern : str_replace($from, $to, $pattern);
    }

    private static function encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next208 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
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
