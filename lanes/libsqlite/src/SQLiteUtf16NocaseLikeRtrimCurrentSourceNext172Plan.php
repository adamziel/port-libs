<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext172Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameYieldTokenPlan(
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
        ?array $lastYielded = null,
        string $currentSource = 'main.wp_options@171',
        string $nextSource = 'main.wp_options@172',
        int $currentSchemaCookie = 171,
        int $nextSchemaCookie = 172,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext165Plan::wordpressOptionNameResumePlan(
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
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentToken = self::tokenRow($base['currentMatchedKeys'], $lastYielded);
        $nextToken = self::tokenRow($base['nextMatchedKeys'], $lastYielded);
        $yieldedReenteredAfterToken = $lastYielded !== null
            && in_array($lastYielded['rowid'], $base['nextAfterTokenRowids'], true);
        $yieldedMissingInNext = $lastYielded !== null
            && array_key_exists($lastYielded['rowid'], $base['currentMatchedKeys'])
            && !array_key_exists($lastYielded['rowid'], $base['nextMatchedKeys']);

        $yieldTokenReasons = [];
        if ($yieldedReenteredAfterToken) {
            $yieldTokenReasons[] = 'yielded-token-reentered-after-token';
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['key'] !== $nextToken['key']) {
            $yieldTokenReasons[] = 'yielded-key-changed';
        }
        if ($yieldedMissingInNext) {
            $yieldTokenReasons[] = 'yielded-row-exited';
        }

        $resumeReasons = $base['resumeReasons'];
        if ($yieldedReenteredAfterToken && !in_array('yielded-token-reentered-after-token', $resumeReasons, true)) {
            $resumeReasons[] = 'yielded-token-reentered-after-token';
        }
        $mustReprepare = $resumeReasons !== [];
        $resumeRows = $mustReprepare ? $base['nextMatchedRowids'] : self::withoutRowid($base['nextAfterTokenRowids'], $lastYielded['rowid'] ?? null);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next172',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'lastYielded' => $lastYielded,
            'currentTokenRow' => $currentToken,
            'nextTokenRow' => $nextToken,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentAfterTokenRowids' => $base['currentAfterTokenRowids'],
            'nextAfterTokenRowids' => $base['nextAfterTokenRowids'],
            'nextBeforeOrAtTokenRowids' => $base['nextBeforeOrAtTokenRowids'],
            'yieldedReenteredAfterToken' => $yieldedReenteredAfterToken,
            'yieldedMissingInNext' => $yieldedMissingInNext,
            'yieldTokenReasons' => array_values(array_unique($yieldTokenReasons)),
            'baseResumeReasons' => $base['resumeReasons'],
            'resumeReasons' => array_values(array_unique($resumeReasons)),
            'semanticInvalidationReasons' => $base['semanticInvalidationReasons'],
            'byteReprepareReasons' => $base['byteReprepareReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'mustReprepareBeforeResume' => $mustReprepare,
            'safeToResumeFromToken' => !$mustReprepare,
            'resumePlanRowids' => $resumeRows,
            'resumePlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-last-yielded-key-rowid',
            'avoidsDuplicateYieldOfTokenRowid' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-current-source-yield-token',
                'sqlite-current-source-next172',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE/RTRIM resume ordering, and adds yielded-token duplicate prevention diagnostics',
        ];
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return array{rowid:int,key:string}|null */
    private static function tokenRow(array $keys, ?array $token): ?array
    {
        if ($token === null || !array_key_exists($token['rowid'], $keys)) {
            return null;
        }

        return [
            'rowid' => $token['rowid'],
            'key' => $keys[$token['rowid']],
        ];
    }

    /** @param list<int> $rowids @return list<int> */
    private static function withoutRowid(array $rowids, ?int $rowid): array
    {
        if ($rowid === null) {
            return $rowids;
        }

        return array_values(array_filter($rowids, static fn (int $candidate): bool => $candidate !== $rowid));
    }
}
