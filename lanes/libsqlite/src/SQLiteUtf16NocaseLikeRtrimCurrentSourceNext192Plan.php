<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext192Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameCandidateTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache_old', 'rowid' => 6],
        string $currentSource = 'main.wp_options@191',
        string $nextSource = 'main.wp_options@192',
        int $currentSchemaCookie = 191,
        int $nextSchemaCookie = 192,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = self::normalizeToken($resumeToken);
        $currentCandidateBefore = self::candidateBeforeOrAt($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextCandidateBefore = self::candidateBeforeOrAt($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentFalseBefore = array_values(array_intersect($currentCandidateBefore, $base['currentRangeFalsePositiveRowids']));
        $nextFalseBefore = array_values(array_intersect($nextCandidateBefore, $base['nextRangeFalsePositiveRowids']));
        $currentMatchedBefore = array_values(array_intersect($currentCandidateBefore, $base['currentMatchedRowids']));
        $nextMatchedBefore = array_values(array_intersect($nextCandidateBefore, $base['nextMatchedRowids']));
        $nextReplayAfter = self::candidateAfter($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);

        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($token === null) {
            $unsafe[] = 'yield-token-missing';
        } elseif ($token['normalizationReasons'] !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if ($currentCandidateBefore !== $nextCandidateBefore) {
            $unsafe[] = 'candidate-before-token-changed';
        }
        if ($currentFalseBefore !== $nextFalseBefore) {
            $unsafe[] = 'false-positive-before-token-changed';
        }
        if ($currentMatchedBefore !== $nextMatchedBefore) {
            $unsafe[] = 'matched-before-token-changed';
        }
        if ($token !== null
            && !in_array($token['rowid'], $base['nextCandidateRowids'], true)
            && !in_array($token['rowid'], $base['nextMatchedRowids'], true)) {
            $unsafe[] = 'yield-token-row-missing';
        }

        $safe = $unsafe === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next192',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* candidate residual token */',
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
            'currentCandidateBeforeOrAtTokenRowids' => $currentCandidateBefore,
            'nextCandidateBeforeOrAtTokenRowids' => $nextCandidateBefore,
            'currentFalsePositiveBeforeOrAtTokenRowids' => $currentFalseBefore,
            'nextFalsePositiveBeforeOrAtTokenRowids' => $nextFalseBefore,
            'currentMatchedBeforeOrAtTokenRowids' => $currentMatchedBefore,
            'nextMatchedBeforeOrAtTokenRowids' => $nextMatchedBefore,
            'nextReplayCandidateRowidsAfterToken' => $nextReplayAfter,
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'candidateTokenUnsafeReasons' => array_values(array_unique($unsafe)),
            'candidateTokenResumeSafe' => $safe,
            'mustReprepareBeforeCandidateTokenResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-candidate-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? $nextReplayAfter : $base['nextCandidateRowids'],
            'residualRecheckRequiredForCandidates' => $base['currentRangeFalsePositiveRowids'] !== [] || $base['nextRangeFalsePositiveRowids'] !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-candidate-token',
                'sqlite-like-residual-recheck',
                'sqlite-current-source-next192',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and residual LIKE rechecks',
            'non_overlap' => 'next192 adds candidate-token resume safety when UTF-16 RTRIM/NOCASE LIKE range rows include residual false positives; avoids accepted next183 prefix reuse, next187 dangling ESCAPE, next189 peer-window matched-row resume, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next192 resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next192 resume token requires integer rowid');
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
    private static function candidateBeforeOrAt(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static function (int $rowid) use ($keys, $token): bool {
            $key = $keys[$rowid] ?? null;
            if ($key === null) {
                return false;
            }
            $comparison = strcmp($key, $token['key']);

            return $comparison < 0 || ($comparison === 0 && $rowid <= $token['rowid']);
        }));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function candidateAfter(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static function (int $rowid) use ($keys, $token): bool {
            $key = $keys[$rowid] ?? null;
            if ($key === null) {
                return false;
            }
            $comparison = strcmp($key, $token['key']);

            return $comparison > 0 || ($comparison === 0 && $rowid > $token['rowid']);
        }));
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
