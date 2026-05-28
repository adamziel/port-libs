<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext164Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameYieldPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@163',
        string $nextSource = 'main.wp_options@164',
        int $currentSchemaCookie = 163,
        int $nextSchemaCookie = 164,
        string $currentCollationGeneration = 'NOCASE/RTRIM@163',
        string $nextCollationGeneration = 'NOCASE/RTRIM@164',
        string $currentLikeGeneration = 'like@163',
        string $nextLikeGeneration = 'like@164',
        string $currentPreparedStatement = 'select-rtrim-nocase-like@163',
        string $nextPreparedStatement = 'select-rtrim-nocase-like@164',
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext161Plan::wordpressOptionNamePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
            $currentCollationGeneration,
            $nextCollationGeneration,
            $currentLikeGeneration,
            $nextLikeGeneration,
        );

        $retainedCandidates = array_values(array_intersect($base['currentCandidateRowids'], $base['nextCandidateRowids']));
        $retainedMatched = $base['retainedMatchedRowids'];
        $stableRows = [];
        $recheckRows = [];
        $resumeKeys = [];

        foreach ($retainedCandidates as $rowid) {
            $stable = self::same($base, 'currentRtrimTexts', 'nextRtrimTexts', $rowid)
                && self::same($base, 'currentNocaseKeys', 'nextNocaseKeys', $rowid)
                && self::same($base, 'currentEncodings', 'nextEncodings', $rowid)
                && self::same($base, 'currentBytesHex', 'nextBytesHex', $rowid);

            if ($stable) {
                $stableRows[] = $rowid;
            } else {
                $recheckRows[] = $rowid;
            }

            $resumeKeys[$rowid] = self::rowFingerprint($base, $rowid);
        }

        $rangeFingerprint = self::rangeFingerprint($base['range']);
        $currentStatementFingerprint = self::statementFingerprint(
            $currentPreparedStatement,
            $currentSource,
            $currentSchemaCookie,
            $currentCollationGeneration,
            $currentLikeGeneration,
            $rangeFingerprint,
        );
        $nextStatementFingerprint = self::statementFingerprint(
            $nextPreparedStatement,
            $nextSource,
            $nextSchemaCookie,
            $nextCollationGeneration,
            $nextLikeGeneration,
            $rangeFingerprint,
        );

        $reasons = $base['invalidationReasons'];
        if ($currentPreparedStatement !== $nextPreparedStatement) {
            $reasons[] = 'prepared-statement-token';
        }
        if ($currentStatementFingerprint !== $nextStatementFingerprint) {
            $reasons[] = 'statement-fingerprint';
        }
        if ($recheckRows !== []) {
            $reasons[] = 'yield-retained-row-recheck';
        }
        if ($base['currentCandidateRowids'] !== $base['nextCandidateRowids']) {
            $reasons[] = 'yield-candidate-position';
        }
        if ($base['currentMatchedRowids'] !== $base['nextMatchedRowids']) {
            $reasons[] = 'yield-output-position';
        }

        $resumeSafe = $reasons === []
            && $base['cursorReusable'] === true
            && $stableRows === $retainedCandidates
            && $base['currentMalformedRowids'] === []
            && $base['nextMalformedRowids'] === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next164',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationGeneration' => $currentCollationGeneration,
            'nextCollationGeneration' => $nextCollationGeneration,
            'currentLikeGeneration' => $currentLikeGeneration,
            'nextLikeGeneration' => $nextLikeGeneration,
            'currentPreparedStatement' => $currentPreparedStatement,
            'nextPreparedStatement' => $nextPreparedStatement,
            'range' => $base['range'],
            'rangeFingerprint' => $rangeFingerprint,
            'currentStatementFingerprint' => $currentStatementFingerprint,
            'nextStatementFingerprint' => $nextStatementFingerprint,
            'indexUsable' => $base['indexUsable'],
            'prefix' => $base['prefix'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'retainedCandidateRowids' => $retainedCandidates,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'retainedMatchedRowids' => $retainedMatched,
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'yieldStableRetainedRowids' => $stableRows,
            'yieldRecheckRetainedRowids' => $recheckRows,
            'yieldSkippedCurrentRowids' => array_values(array_diff($base['currentCandidateRowids'], $retainedCandidates)),
            'yieldNewNextRowids' => array_values(array_diff($base['nextCandidateRowids'], $retainedCandidates)),
            'yieldResumeKeyFingerprints' => $resumeKeys,
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentEncodings' => $base['currentEncodings'],
            'nextEncodings' => $base['nextEncodings'],
            'currentBytesHex' => $base['currentBytesHex'],
            'nextBytesHex' => $base['nextBytesHex'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $resumeSafe,
            'yieldResumeSafe' => $resumeSafe,
            'yieldResumeRequiresReprepare' => $reasons !== [],
            'yieldResumeRequiresResidualRecheck' => $recheckRows !== [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-next161',
                'sqlite-yield-current-source-next164',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM/NOCASE LIKE scans, and adds yield/resume statement fingerprints for current-source transitions',
        ];
    }

    /** @param array<string,mixed> $base */
    private static function same(array $base, string $currentKey, string $nextKey, int $rowid): bool
    {
        return ($base[$currentKey][$rowid] ?? null) === ($base[$nextKey][$rowid] ?? null);
    }

    /** @param array<string,mixed> $base */
    private static function rowFingerprint(array $base, int $rowid): string
    {
        return hash('sha256', json_encode([
            'rowid' => $rowid,
            'rtrim' => $base['nextRtrimTexts'][$rowid] ?? null,
            'nocase' => $base['nextNocaseKeys'][$rowid] ?? null,
            'encoding' => $base['nextEncodings'][$rowid] ?? null,
            'bytes' => $base['nextBytesHex'][$rowid] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function rangeFingerprint(?array $range): string
    {
        return hash('sha256', json_encode($range, JSON_THROW_ON_ERROR));
    }

    private static function statementFingerprint(
        string $statement,
        string $source,
        int $schemaCookie,
        string $collationGeneration,
        string $likeGeneration,
        string $rangeFingerprint,
    ): string {
        return hash('sha256', json_encode([
            'statement' => $statement,
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'collationGeneration' => $collationGeneration,
            'likeGeneration' => $likeGeneration,
            'rangeFingerprint' => $rangeFingerprint,
        ], JSON_THROW_ON_ERROR));
    }
}
