<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext228Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameHeaderEncodingFencePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        int|string $currentDatabaseEncoding = 'UTF-16LE',
        int|string $nextDatabaseEncoding = 'UTF-16BE',
        int|string $preparedEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options@227',
        string $nextSource = 'main.wp_options@228',
        int $currentSchemaCookie = 227,
        int $nextSchemaCookie = 228,
    ): array {
        $currentEncoding = self::encodingName($currentDatabaseEncoding);
        $nextEncoding = self::encodingName($nextDatabaseEncoding);
        $statementEncoding = self::encodingName($preparedEncoding);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext211Plan::wordpressOptionNameSourceRefreshPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $headerEncodingChanged = $currentEncoding !== $nextEncoding;
        $preparedMatchesCurrent = $statementEncoding === $currentEncoding;
        $preparedMatchesNext = $statementEncoding === $nextEncoding;
        $logicalRowsetStable = $base['currentCandidateRowids'] === $base['nextCandidateRowids']
            && $base['currentMatchedRowids'] === $base['nextMatchedRowids']
            && $base['decodedRtrimTextChangedRowids'] === []
            && $base['currentMalformedRowids'] === []
            && $base['nextMalformedRowids'] === [];

        $reasons = $base['invalidationReasons'];
        if ($headerEncodingChanged) {
            $reasons[] = 'database-text-encoding';
        }
        if ($preparedMatchesCurrent && !$preparedMatchesNext) {
            $reasons[] = 'prepared-encoding-stale';
        }
        if ($headerEncodingChanged && $logicalRowsetStable) {
            $reasons[] = 'logical-rowset-stable-header-fence';
        }
        $reasons = array_values(array_unique($reasons));

        $baseReasonsWithoutByteOrderOnly = array_values(array_diff($base['invalidationReasons'], ['byte-order-only-refresh']));
        $requiresReprepare = $baseReasonsWithoutByteOrderOnly !== []
            || $headerEncodingChanged
            || ($preparedMatchesCurrent && !$preparedMatchesNext);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next228',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* database text-encoding fence */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $base['collation'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentDatabaseEncoding' => $currentEncoding,
            'nextDatabaseEncoding' => $nextEncoding,
            'preparedEncoding' => $statementEncoding,
            'headerEncodingChanged' => $headerEncodingChanged,
            'preparedEncodingMatchesCurrentHeader' => $preparedMatchesCurrent,
            'preparedEncodingMatchesNextHeader' => $preparedMatchesNext,
            'logicalRowsetStable' => $logicalRowsetStable,
            'baseByteOrderOnlyRefreshReusable' => $base['byteOrderOnlyRefreshReusable'],
            'baseCursorReusable' => $base['cursorReusable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'byteOrderOnlyRowids' => $base['byteOrderOnlyRowids'],
            'encodingChangedRowids' => $base['encodingChangedRowids'],
            'decodedRtrimTextChangedRowids' => $base['decodedRtrimTextChangedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'invalidationReasons' => $reasons,
            'cursorInvalidated' => $requiresReprepare,
            'cursorReusable' => !$requiresReprepare,
            'mustReprepareForHeaderEncoding' => $headerEncodingChanged,
            'mustRepreparePreparedUtf16Statement' => $preparedMatchesCurrent && !$preparedMatchesNext,
            'canRetainRowsetButNotPreparedCursor' => $logicalRowsetStable && $requiresReprepare,
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'residualCheckedAfterRtrim' => $base['residualCheckedAfterRtrim'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-database-text-encoding-header',
                'sqlite-prepared-statement-encoding-fence',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next228',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, accepted NOCASE/RTRIM LIKE residuals, and adds a bounded database text-encoding header fence for prepared cursor reuse',
            'non_overlap' => 'next228 layers a database text-encoding header/prepared-statement fence on top of accepted next211 byte-order-only refresh; it does not repeat next224 keyset resume, next223 DESC LIMIT windows, next221 prepared byte signatures, next208 ESCAPE decoding, Unicode GLOB ranges, or UTF-16 malformed insert guards',
        ];
    }

    private static function encodingName(int|string $encoding): string
    {
        return match ($encoding) {
            1, 'UTF-8' => 'UTF-8',
            2, 'UTF-16LE' => 'UTF-16LE',
            3, 'UTF-16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next228 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
