<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext189Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePeerWindowPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache', 'rowid' => 2],
        string $currentSource = 'main.wp_options@188',
        string $nextSource = 'main.wp_options@189',
        int $currentSchemaCookie = 188,
        int $nextSchemaCookie = 189,
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
        $peerKey = $token['key'] ?? null;
        $currentPeers = $peerKey === null ? [] : self::rowidsForKey($base['currentNocaseKeys'], $peerKey, $base['currentMatchedRowids']);
        $nextPeers = $peerKey === null ? [] : self::rowidsForKey($base['nextNocaseKeys'], $peerKey, $base['nextMatchedRowids']);
        $currentBeforeOrAt = $token === null ? [] : self::beforeOrAt($currentPeers, $token['rowid']);
        $nextBeforeOrAt = $token === null ? [] : self::beforeOrAt($nextPeers, $token['rowid']);
        $currentAfter = $token === null ? [] : self::after($currentPeers, $token['rowid']);
        $nextAfter = $token === null ? [] : self::after($nextPeers, $token['rowid']);
        $peerDeleted = array_values(array_diff($currentPeers, $nextPeers));
        $peerInserted = array_values(array_diff($nextPeers, $currentPeers));
        $paddingOnly = self::paddingOnlyRowids($base);
        $residualChanged = self::residualChangedRowids($base);

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
        if ($nextBeforeOrAt !== $currentBeforeOrAt) {
            $unsafe[] = 'peer-before-token-changed';
        }
        if ($residualChanged !== []) {
            $unsafe[] = 'like-residual-rowset-changed';
        }

        $safe = $unsafe === [];
        $replay = $safe ? array_values(array_merge($nextAfter, self::afterKeyRowids($base['nextNocaseKeys'], $base['nextMatchedRowids'], $peerKey))) : $base['nextMatchedRowids'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next189',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* peer window */',
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
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'resumeToken' => $token,
            'peerKey' => $peerKey,
            'currentPeerRowids' => $currentPeers,
            'nextPeerRowids' => $nextPeers,
            'currentPeerBeforeOrAtTokenRowids' => $currentBeforeOrAt,
            'nextPeerBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'currentPeerAfterTokenRowids' => $currentAfter,
            'nextPeerAfterTokenRowids' => $nextAfter,
            'peerDeletedRowids' => $peerDeleted,
            'peerInsertedRowids' => $peerInserted,
            'paddingOnlyStableRowids' => $paddingOnly,
            'residualChangedRowids' => $residualChanged,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'peerWindowUnsafeReasons' => array_values(array_unique($unsafe)),
            'peerWindowResumeSafe' => $safe,
            'mustReprepareBeforePeerWindowResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-rtrim-nocase-peer-window' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replay,
            'rtrimPaddingOnlyKeepsPeerKey' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-peer-window',
                'sqlite-current-source-next189',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and current-source peer-window replay diagnostics',
            'non_overlap' => 'next189 adds peer-window rowid tie-breaker diagnostics for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted deleted-token resume next185, resume-boundary next186, escaped residual token next184, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next189 resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next189 resume token requires integer rowid');
        }

        $key = self::asciiLower(rtrim($token['key'], ' '));

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'normalizationReasons' => $key === $token['key'] ? [] : ['token-key-not-canonical'],
        ];
    }

    /** @param array<int,string> $keys @param list<int> $matchedRowids @return list<int> */
    private static function rowidsForKey(array $keys, string $key, array $matchedRowids): array
    {
        return array_values(array_filter($matchedRowids, static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $key));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function beforeOrAt(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid <= $tokenRowid));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function after(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid > $tokenRowid));
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function paddingOnlyRowids(array $base): array
    {
        $stable = [];
        foreach ($base['changedTextRowids'] as $rowid) {
            if (($base['currentRtrimTexts'][$rowid] ?? null) === ($base['nextRtrimTexts'][$rowid] ?? null)
                && ($base['currentNocaseKeys'][$rowid] ?? null) === ($base['nextNocaseKeys'][$rowid] ?? null)) {
                $stable[] = (int) $rowid;
            }
        }

        return $stable;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function residualChangedRowids(array $base): array
    {
        $changed = array_values(array_unique(array_merge(
            $base['matchedEnteredRowids'],
            $base['matchedExitedRowids'],
            $base['currentRangeFalsePositiveRowids'],
            $base['nextRangeFalsePositiveRowids'],
        )));
        sort($changed);

        return $changed;
    }

    /** @param array<int,string> $keys @param list<int> $matchedRowids @return list<int> */
    private static function afterKeyRowids(array $keys, array $matchedRowids, ?string $key): array
    {
        if ($key === null) {
            return [];
        }

        return array_values(array_filter($matchedRowids, static fn (int $rowid): bool => isset($keys[$rowid]) && strcmp($keys[$rowid], $key) > 0));
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
