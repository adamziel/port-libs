<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext218Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameYieldPagePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "!\0",
        int|string $nextEscapeEncoding = 'UTF-16LE',
        int $limit = 3,
        int $offset = 1,
        string $currentSource = 'main.wp_options@217',
        string $nextSource = 'main.wp_options@218',
        int $currentSchemaCookie = 217,
        int $nextSchemaCookie = 218,
        ?array $cursor = null,
    ): array {
        if ($limit < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next218 LIMIT must be positive');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next218 OFFSET must be non-negative');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext208Plan::wordpressOptionNamePreparedEscapePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::orderedMatchedRows($base['currentMatchedRowids'], $base['currentRtrimTexts'], $base['currentNocaseKeys'], $base['currentMatchedTexts']);
        $nextOrdered = self::orderedMatchedRows($base['nextMatchedRowids'], $base['nextRtrimTexts'], $base['nextNocaseKeys'], $base['nextMatchedTexts']);
        $currentPage = array_slice($currentOrdered, $offset, $limit);
        $nextPage = array_slice($nextOrdered, $offset, $limit);
        $currentBefore = array_slice($currentOrdered, 0, $offset);
        $nextBefore = array_slice($nextOrdered, 0, $offset);
        $currentAfter = array_slice($currentOrdered, $offset + $limit);
        $nextAfter = array_slice($nextOrdered, $offset + $limit);
        $currentToken = self::pageToken($currentSource, $currentSchemaCookie, $pattern, $base['currentEscape'], $offset, $limit, $currentPage);

        if ($cursor !== null) {
            self::assertCursor($cursor, $currentToken);
        }

        $pageExited = array_values(array_diff(self::rowids($currentPage), self::rowids($nextPage)));
        $pageEntered = array_values(array_diff(self::rowids($nextPage), self::rowids($currentPage)));
        $beforeChanged = self::rowids($currentBefore) !== self::rowids($nextBefore);
        $pageChanged = self::rowids($currentPage) !== self::rowids($nextPage);
        $afterChanged = self::rowids($currentAfter) !== self::rowids($nextAfter);
        $reasons = $base['invalidationReasons'];
        if ($beforeChanged) {
            $reasons[] = 'rows-before-limit-window';
        }
        if ($pageChanged) {
            $reasons[] = 'limit-window-rowset';
        }
        if ($afterChanged) {
            $reasons[] = 'rows-after-limit-window';
        }
        if ($base['currentEscape'] !== $base['nextEscape'] || $base['currentEscapeBytesHex'] !== $base['nextEscapeBytesHex']) {
            $reasons[] = 'yield-escape-fence';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next218',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ? OFFSET ?',
            'pattern' => $pattern,
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'limit' => $limit,
            'offset' => $offset,
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
            'currentOrderedRowids' => self::rowids($currentOrdered),
            'nextOrderedRowids' => self::rowids($nextOrdered),
            'currentBeforeWindowRowids' => self::rowids($currentBefore),
            'nextBeforeWindowRowids' => self::rowids($nextBefore),
            'currentPageRowids' => self::rowids($currentPage),
            'nextPageRowids' => self::rowids($nextPage),
            'currentAfterWindowRowids' => self::rowids($currentAfter),
            'nextAfterWindowRowids' => self::rowids($nextAfter),
            'pageRetainedRowids' => array_values(array_intersect(self::rowids($currentPage), self::rowids($nextPage))),
            'pageExitedRowids' => $pageExited,
            'pageEnteredRowids' => $pageEntered,
            'rowsBeforeWindowChanged' => $beforeChanged,
            'limitWindowChanged' => $pageChanged,
            'rowsAfterWindowChanged' => $afterChanged,
            'currentPageRows' => $currentPage,
            'nextPageRows' => $nextPage,
            'currentPageTail' => $currentPage === [] ? null : $currentPage[array_key_last($currentPage)],
            'nextPageTail' => $nextPage === [] ? null : $nextPage[array_key_last($nextPage)],
            'currentPageToken' => $currentToken,
            'nextPageToken' => self::pageToken($nextSource, $nextSchemaCookie, $pattern, $base['nextEscape'], $offset, $limit, $nextPage),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleYieldPageRisk' => $beforeChanged || $pageChanged || $base['cursorInvalidated'],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => $base['escapeDecodedBeforeRangePlanning'],
            'limitWindowAppliedAfterResidual' => true,
            'orderUsesRtrimNocaseKeyThenRowid' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-limit-yield-window',
                'sqlite-current-source-next218',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source yield cursor diagnostics',
            'non_overlap' => 'next218 adds LIMIT/OFFSET yield-window fencing after UTF-16 NOCASE/RTRIM LIKE residual matching; avoids accepted next208 prepared ESCAPE decode, next203 no-prefix full scans, next200 escape rebinding, next185/170 resume-token replay, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<int> $rowids
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $nocaseKeys
     * @param array<int,string> $matchedTexts
     * @return list<array{rowid:int,rtrimText:string,nocaseKey:string,matchedText:string}>
     */
    private static function orderedMatchedRows(array $rowids, array $rtrimTexts, array $nocaseKeys, array $matchedTexts): array
    {
        $rows = [];
        foreach ($rowids as $rowid) {
            if (!isset($rtrimTexts[$rowid], $nocaseKeys[$rowid], $matchedTexts[$rowid])) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'rtrimText' => $rtrimTexts[$rowid],
                'nocaseKey' => $nocaseKeys[$rowid],
                'matchedText' => $matchedTexts[$rowid],
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $rows;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $page */
    private static function pageToken(string $source, int $schemaCookie, string $pattern, string $escape, int $offset, int $limit, array $page): array
    {
        $tail = $page === [] ? null : $page[array_key_last($page)];

        return [
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'patternHash' => substr(hash('sha256', $pattern), 0, 16),
            'escapeHash' => substr(hash('sha256', $escape), 0, 16),
            'offset' => $offset,
            'limit' => $limit,
            'pageRowids' => self::rowids($page),
            'tailRowid' => is_array($tail) ? $tail['rowid'] : null,
            'tailKey' => is_array($tail) ? $tail['nocaseKey'] : null,
        ];
    }

    /** @param array<string,mixed> $cursor @param array<string,mixed> $token */
    private static function assertCursor(array $cursor, array $token): void
    {
        foreach (['source', 'schemaCookie', 'patternHash', 'escapeHash', 'offset', 'limit', 'tailRowid', 'tailKey'] as $key) {
            if (($cursor[$key] ?? null) !== $token[$key]) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next218 cursor does not match current source yield page');
            }
        }
        if (($cursor['pageRowids'] ?? null) !== $token['pageRowids']) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next218 cursor does not match current source yield page');
        }
    }
}
