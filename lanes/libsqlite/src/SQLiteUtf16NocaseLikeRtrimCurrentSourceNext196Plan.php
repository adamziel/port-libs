<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext196Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameDuplicatePeerResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache', 'rowid' => 6],
        string $currentSource = 'main.wp_options@195',
        string $nextSource = 'main.wp_options@196',
        int $currentSchemaCookie = 195,
        int $nextSchemaCookie = 196,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext192Plan::wordpressOptionNameCandidateTokenPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $resumeToken,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = self::normalizeToken($resumeToken);
        $currentPeers = self::sameKeyPeers($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextPeers = self::sameKeyPeers($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentPeersBefore = self::sameKeyPeersBeforeOrAt($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextPeersBefore = self::sameKeyPeersBeforeOrAt($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentPeerMatches = array_values(array_intersect($currentPeers, $base['currentMatchedRowids']));
        $nextPeerMatches = array_values(array_intersect($nextPeers, $base['nextMatchedRowids']));
        $currentPeerFalse = array_values(array_intersect($currentPeers, $base['currentRangeFalsePositiveRowids']));
        $nextPeerFalse = array_values(array_intersect($nextPeers, $base['nextRangeFalsePositiveRowids']));
        $nextPeersAfter = self::sameKeyPeersAfter($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);

        $peerReasons = [];
        if ($currentPeersBefore !== $nextPeersBefore) {
            $peerReasons[] = 'duplicate-key-peers-before-token-changed';
        }
        if ($currentPeerMatches !== $nextPeerMatches) {
            $peerReasons[] = 'duplicate-key-matched-peers-changed';
        }
        if ($currentPeerFalse !== $nextPeerFalse) {
            $peerReasons[] = 'duplicate-key-false-positive-peers-changed';
        }
        if ($token !== null && !in_array($token['rowid'], $nextPeers, true)) {
            $peerReasons[] = 'duplicate-key-token-row-missing';
        }

        $unsafe = array_values(array_unique(array_merge($base['candidateTokenUnsafeReasons'], $peerReasons)));
        $safe = $unsafe === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next196',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* duplicate comparison-key peers */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'resumeToken' => $token,
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentRangeFalsePositiveRowids' => $base['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $base['nextRangeFalsePositiveRowids'],
            'currentDuplicatePeerRowids' => $currentPeers,
            'nextDuplicatePeerRowids' => $nextPeers,
            'currentDuplicatePeersBeforeOrAtTokenRowids' => $currentPeersBefore,
            'nextDuplicatePeersBeforeOrAtTokenRowids' => $nextPeersBefore,
            'currentDuplicatePeerMatchedRowids' => $currentPeerMatches,
            'nextDuplicatePeerMatchedRowids' => $nextPeerMatches,
            'currentDuplicatePeerFalsePositiveRowids' => $currentPeerFalse,
            'nextDuplicatePeerFalsePositiveRowids' => $nextPeerFalse,
            'nextDuplicatePeersAfterTokenRowids' => $nextPeersAfter,
            'duplicatePeerUnsafeReasons' => $peerReasons,
            'candidateTokenUnsafeReasons' => $unsafe,
            'candidateTokenResumeSafe' => $safe,
            'mustReprepareBeforeCandidateTokenResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-duplicate-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? array_values(array_merge($nextPeersAfter, self::strictlyAfterKey($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token))) : $base['nextCandidateRowids'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'duplicatePeersOrderedByRowidWithinComparisonKey' => true,
            'residualRecheckRequiredForDuplicatePeers' => $currentPeerFalse !== [] || $nextPeerFalse !== [],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-duplicate-peer-key',
                'sqlite-like-residual-recheck',
                'sqlite-current-source-next196',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and candidate-token residual rechecks',
            'non_overlap' => 'next196 adds duplicate comparison-key peer safety for yielded UTF-16 RTRIM/NOCASE LIKE scans; it avoids accepted next192 false-positive token replay, next191 prepared pattern rebind, next183 prefix reuse, malformed UTF-16 guards, Unicode GLOB ranges, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next196 resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next196 resume token requires integer rowid');
        }

        $key = self::asciiLower(rtrim($token['key'], ' '));

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'normalizationReasons' => $key === $token['key'] ? [] : ['token-key-not-canonical'],
        ];
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function sameKeyPeers(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key']));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function sameKeyPeersBeforeOrAt(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key'] && $rowid <= $token['rowid'],
        ));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function sameKeyPeersAfter(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key'] && $rowid > $token['rowid'],
        ));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function strictlyAfterKey(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => isset($keys[$rowid]) && strcmp($keys[$rowid], $token['key']) > 0,
        ));
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
