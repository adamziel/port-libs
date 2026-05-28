<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext186Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameResumeBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@185',
        string $nextSource = 'main.wp_options@186',
        int $currentSchemaCookie = 185,
        int $nextSchemaCookie = 186,
        ?array $resumeToken = null,
    ): array {
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Plan::wordpressOptionNameAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentResume = self::resumeRows($plan['currentNocaseKeys'], $plan['currentRtrimTexts'], $plan['currentCandidateRowids'], $resumeToken);
        $nextResume = self::resumeRows($plan['nextNocaseKeys'], $plan['nextRtrimTexts'], $plan['nextCandidateRowids'], $resumeToken);
        $semanticStable = self::semanticStableRowids($plan);
        $semanticChanged = self::semanticChangedRowids($plan);
        $resumeBoundaryChanged = self::resumeBoundaryChangedRowids($currentResume, $nextResume);
        $byteOnlyChanged = array_values(array_diff($plan['changedBytesRowids'], $semanticChanged));
        sort($byteOnlyChanged);

        $reasons = $plan['invalidationReasons'];
        foreach ([
            'resume-boundary-rowset' => $resumeBoundaryChanged,
            'byte-order-only-source-refresh' => $byteOnlyChanged,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        $plan['status'] = 'utf16-nocase-like-rtrim-current-source-next186';
        $plan['expression'] = 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* resume boundary */';
        $plan['resumeToken'] = $resumeToken;
        $plan['currentResumeRowids'] = self::rowids($currentResume);
        $plan['nextResumeRowids'] = self::rowids($nextResume);
        $plan['currentResumeKeys'] = self::keysByRowid($currentResume);
        $plan['nextResumeKeys'] = self::keysByRowid($nextResume);
        $plan['resumeBoundaryChangedRowids'] = $resumeBoundaryChanged;
        $plan['semanticStableRowids'] = $semanticStable;
        $plan['semanticChangedRowids'] = $semanticChanged;
        $plan['byteOrderOnlyChangedRowids'] = $byteOnlyChanged;
        $plan['safeToResumeAfterToken'] = $resumeBoundaryChanged === [] && $plan['currentMalformedRowids'] === [] && $plan['nextMalformedRowids'] === [];
        $plan['mustReopenSourceCursor'] = !$plan['safeToResumeAfterToken'] || $plan['staleRangeCursorRisk'];
        $plan['resumeKeepsRtrimAsciiOnly'] = true;
        $plan['resumeKeepsNocaseAsciiOnly'] = true;
        $plan['utf16ByteOrderCanChangeWithoutSemanticKeyChange'] = true;
        $plan['invalidationReasons'] = array_values(array_unique($reasons));
        $plan['cursorInvalidated'] = $plan['invalidationReasons'] !== [];
        $plan['cursorReusable'] = $plan['invalidationReasons'] === [];
        $plan['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-residual-match',
            'sqlite-current-source-next186',
            'sqlite-utf16-resume-boundary',
        ];
        $plan['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM residual matching, and adds resume-boundary diagnostics for current-source cursor refresh';
        $plan['non_overlap'] = 'next186 adds resume-boundary and byte-order-only source refresh diagnostics over UTF-16 NOCASE LIKE RTRIM range scans; it avoids accepted next177 Unicode wildcard, next180 non-ASCII prefix fallback, next183 basic ASCII prefix range, and Unicode GLOB behavior';

        return $plan;
    }

    /** @param array<int,string> $keys @param array<int,string> $texts @param list<int> $candidateRowids @param ?array{key:string,rowid:int} $resumeToken @return list<array{rowid:int,key:string,text:string}> */
    private static function resumeRows(array $keys, array $texts, array $candidateRowids, ?array $resumeToken): array
    {
        if ($resumeToken !== null) {
            if (!isset($resumeToken['key']) || !is_string($resumeToken['key'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next186 resume token requires string key');
            }
            if (!isset($resumeToken['rowid']) || !is_int($resumeToken['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next186 resume token requires integer rowid');
            }
        }

        $rows = [];
        foreach ($candidateRowids as $rowid) {
            $key = $keys[$rowid] ?? null;
            if (!is_string($key)) {
                continue;
            }
            if ($resumeToken !== null) {
                $comparison = strcmp($key, $resumeToken['key']);
                if ($comparison < 0 || ($comparison === 0 && $rowid <= $resumeToken['rowid'])) {
                    continue;
                }
            }
            $text = $texts[$rowid] ?? '';
            $rows[] = ['rowid' => $rowid, 'key' => $key, 'text' => is_string($text) ? $text : ''];
        }

        return $rows;
    }

    /** @param array<string,mixed> $plan @return list<int> */
    private static function semanticStableRowids(array $plan): array
    {
        $current = array_keys($plan['currentNocaseKeys']);
        $next = array_keys($plan['nextNocaseKeys']);
        $rowids = array_values(array_intersect($current, $next));
        sort($rowids);
        $stable = [];
        foreach ($rowids as $rowid) {
            if (($plan['currentRtrimTexts'][$rowid] ?? null) === ($plan['nextRtrimTexts'][$rowid] ?? null)
                && ($plan['currentNocaseKeys'][$rowid] ?? null) === ($plan['nextNocaseKeys'][$rowid] ?? null)) {
                $stable[] = (int) $rowid;
            }
        }

        return $stable;
    }

    /** @param array<string,mixed> $plan @return list<int> */
    private static function semanticChangedRowids(array $plan): array
    {
        $changed = array_values(array_unique(array_merge(
            $plan['changedTextRowids'],
            $plan['changedRtrimRowids'],
            $plan['changedNocaseKeyRowids'],
            $plan['rtrimResidualChangedRowids'],
            $plan['matchedEnteredRowids'],
            $plan['matchedExitedRowids'],
            $plan['currentRangeExitedRowids'],
            $plan['nextRangeEnteredRowids'],
        )));
        sort($changed);

        return $changed;
    }

    /** @param list<array{rowid:int,key:string,text:string}> $current @param list<array{rowid:int,key:string,text:string}> $next @return list<int> */
    private static function resumeBoundaryChangedRowids(array $current, array $next): array
    {
        $changed = array_values(array_unique(array_merge(
            array_diff(self::rowids($current), self::rowids($next)),
            array_diff(self::rowids($next), self::rowids($current)),
        )));
        sort($changed);

        return $changed;
    }

    /** @param list<array{rowid:int,key:string,text:string}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array{rowid:int,key:string,text:string}> $rows @return array<int,string> */
    private static function keysByRowid(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['rowid']] = $row['key'];
        }

        return $keys;
    }
}
