<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext161Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@160',
        string $nextSource = 'main.wp_options@161',
        int $currentSchemaCookie = 160,
        int $nextSchemaCookie = 161,
        string $currentCollationGeneration = 'NOCASE/RTRIM@160',
        string $nextCollationGeneration = 'NOCASE/RTRIM@161',
        string $currentLikeGeneration = 'like@160',
        string $nextLikeGeneration = 'like@161',
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Plan::wordpressOptionNamePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $retained = array_values(array_intersect($base['currentMatchedRowids'], $base['nextMatchedRowids']));
        $retainedChanges = self::retainedChanges($retained, $base);
        $reasons = $base['invalidationReasons'];

        if ($currentCollationGeneration !== $nextCollationGeneration) {
            $reasons[] = 'collation-generation';
        }
        if ($currentLikeGeneration !== $nextLikeGeneration) {
            $reasons[] = 'like-generation';
        }
        foreach ([
            'retained-rtrim-key' => $retainedChanges['rtrim'],
            'retained-nocase-key' => $retainedChanges['nocase'],
            'retained-encoding' => $retainedChanges['encoding'],
            'retained-bytes' => $retainedChanges['bytes'],
        ] as $reason => $rowids) {
            if ($rowids !== [] && !in_array($reason, $reasons, true)) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next161',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationGeneration' => $currentCollationGeneration,
            'nextCollationGeneration' => $nextCollationGeneration,
            'currentLikeGeneration' => $currentLikeGeneration,
            'nextLikeGeneration' => $nextLikeGeneration,
            'baseStatus' => $base['status'],
            'indexUsable' => $base['indexUsable'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'currentOrderRowids' => $base['currentOrderRowids'],
            'nextOrderRowids' => $base['nextOrderRowids'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'retainedMatchedRowids' => $retained,
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentEncodings' => $base['currentEncodings'],
            'nextEncodings' => $base['nextEncodings'],
            'currentBytesHex' => $base['currentBytesHex'],
            'nextBytesHex' => $base['nextBytesHex'],
            'retainedChangedRtrimRowids' => $retainedChanges['rtrim'],
            'retainedChangedNocaseRowids' => $retainedChanges['nocase'],
            'retainedChangedEncodingRowids' => $retainedChanges['encoding'],
            'retainedChangedBytesRowids' => $retainedChanges['bytes'],
            'sameSourceToken' => $currentSource === $nextSource && $currentSchemaCookie === $nextSchemaCookie,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $base['cursorReusable'],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'currentSourceMayReuseStatement' => false,
            'reprepareRequired' => $reasons !== [],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-collation-generation',
                'sqlite-current-source-next161',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 row decode, RTRIM/NOCASE LIKE current-source scans, and adds statement invalidation metadata for collation/LIKE generation changes',
        ];
    }

    /**
     * @param list<int> $retained
     * @param array<string,mixed> $base
     * @return array{rtrim:list<int>,nocase:list<int>,encoding:list<int>,bytes:list<int>}
     */
    private static function retainedChanges(array $retained, array $base): array
    {
        $changes = ['rtrim' => [], 'nocase' => [], 'encoding' => [], 'bytes' => []];
        foreach ($retained as $rowid) {
            if (($base['currentRtrimTexts'][$rowid] ?? null) !== ($base['nextRtrimTexts'][$rowid] ?? null)) {
                $changes['rtrim'][] = $rowid;
            }
            if (($base['currentNocaseKeys'][$rowid] ?? null) !== ($base['nextNocaseKeys'][$rowid] ?? null)) {
                $changes['nocase'][] = $rowid;
            }
            if (($base['currentEncodings'][$rowid] ?? null) !== ($base['nextEncodings'][$rowid] ?? null)) {
                $changes['encoding'][] = $rowid;
            }
            if (($base['currentBytesHex'][$rowid] ?? null) !== ($base['nextBytesHex'][$rowid] ?? null)) {
                $changes['bytes'][] = $rowid;
            }
        }

        return $changes;
    }
}
