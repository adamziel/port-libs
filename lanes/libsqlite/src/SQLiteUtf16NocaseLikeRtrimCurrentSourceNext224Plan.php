<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext224Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed>|null $resumeToken
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameKeysetResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "!\0",
        int|string $nextEscapeEncoding = 'UTF-16LE',
        int $pageSize = 3,
        int $lastRowid = 0,
        ?string $lastKey = null,
        string $currentSource = 'main.wp_options@223',
        string $nextSource = 'main.wp_options@224',
        int $currentSchemaCookie = 223,
        int $nextSchemaCookie = 224,
        ?array $resumeToken = null,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next224 page size must be positive');
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
        if ($lastKey === null) {
            $tail = $currentOrdered === [] ? null : $currentOrdered[0];
            $lastKey = is_array($tail) ? $tail['nocaseKey'] : '';
            $lastRowid = is_array($tail) ? $tail['rowid'] : 0;
        }
        $currentBefore = self::rowsAtOrBefore($currentOrdered, $lastKey, $lastRowid);
        $nextBefore = self::rowsAtOrBefore($nextOrdered, $lastKey, $lastRowid);
        $currentRemaining = self::rowsAfter($currentOrdered, $lastKey, $lastRowid);
        $nextRemaining = self::rowsAfter($nextOrdered, $lastKey, $lastRowid);
        $currentPage = array_slice($currentRemaining, 0, $pageSize);
        $nextPage = array_slice($nextRemaining, 0, $pageSize);
        $currentToken = self::resumeToken($currentSource, $currentSchemaCookie, $pattern, $base['currentEscape'], $lastKey, $lastRowid, $pageSize, $currentPage);

        if ($resumeToken !== null) {
            self::assertResumeToken($resumeToken, $currentToken);
        }

        $reasons = $base['invalidationReasons'];
        if (self::rowids($currentBefore) !== self::rowids($nextBefore)) {
            $reasons[] = 'resume-prefix-rowset';
        }
        if (self::rowids($currentPage) !== self::rowids($nextPage)) {
            $reasons[] = 'resume-page-rowset';
        }
        if (self::rowids($currentRemaining) !== self::rowids($nextRemaining)) {
            $reasons[] = 'resume-tail-rowset';
        }
        if ($base['currentEscape'] !== $base['nextEscape'] || $base['currentEscapeBytesHex'] !== $base['nextEscapeBytesHex']) {
            $reasons[] = 'resume-escape-fence';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next224',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? AND (rtrim(option_name) COLLATE NOCASE, rowid) > (?, ?) ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ?',
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
            'pageSize' => $pageSize,
            'lastKey' => $lastKey,
            'lastRowid' => $lastRowid,
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
            'currentRowsAtOrBeforeResume' => self::rowids($currentBefore),
            'nextRowsAtOrBeforeResume' => self::rowids($nextBefore),
            'currentRemainingRowids' => self::rowids($currentRemaining),
            'nextRemainingRowids' => self::rowids($nextRemaining),
            'currentResumePageRowids' => self::rowids($currentPage),
            'nextResumePageRowids' => self::rowids($nextPage),
            'resumePageRetainedRowids' => array_values(array_intersect(self::rowids($currentPage), self::rowids($nextPage))),
            'resumePageExitedRowids' => array_values(array_diff(self::rowids($currentPage), self::rowids($nextPage))),
            'resumePageEnteredRowids' => array_values(array_diff(self::rowids($nextPage), self::rowids($currentPage))),
            'currentResumePageRows' => $currentPage,
            'nextResumePageRows' => $nextPage,
            'currentResumeToken' => $currentToken,
            'nextResumeToken' => self::resumeToken($nextSource, $nextSchemaCookie, $pattern, $base['nextEscape'], $lastKey, $lastRowid, $pageSize, $nextPage),
            'resumePrefixChanged' => self::rowids($currentBefore) !== self::rowids($nextBefore),
            'resumePageChanged' => self::rowids($currentPage) !== self::rowids($nextPage),
            'resumeTailChanged' => self::rowids($currentRemaining) !== self::rowids($nextRemaining),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleKeysetResumeRisk' => $reasons !== [],
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
            'keysetResumeAppliedAfterResidual' => true,
            'orderUsesRtrimNocaseKeyThenRowid' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-keyset-resume',
                'sqlite-current-source-next224',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source keyset cursor diagnostics',
            'non_overlap' => 'next224 adds keyset resume fencing for UTF-16 NOCASE/RTRIM LIKE cursors after a saved (rtrim-nocase-key,rowid) tail; avoids accepted next218 LIMIT/OFFSET yield-window, next208 prepared ESCAPE decode, next203 no-prefix full scans, next200 escape rebinding, Unicode GLOB ranges, and UTF-16 malformed insert guards',
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

    /** @param list<array{rowid:int,nocaseKey:string}> $rows @return list<array{rowid:int,nocaseKey:string}> */
    private static function rowsAtOrBefore(array $rows, string $lastKey, int $lastRowid): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::compareKeyset($row, $lastKey, $lastRowid) <= 0));
    }

    /** @param list<array{rowid:int,nocaseKey:string}> $rows @return list<array{rowid:int,nocaseKey:string}> */
    private static function rowsAfter(array $rows, string $lastKey, int $lastRowid): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::compareKeyset($row, $lastKey, $lastRowid) > 0));
    }

    /** @param array{rowid:int,nocaseKey:string} $row */
    private static function compareKeyset(array $row, string $lastKey, int $lastRowid): int
    {
        $key = strcmp($row['nocaseKey'], $lastKey);

        return $key !== 0 ? $key : $row['rowid'] <=> $lastRowid;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $page */
    private static function resumeToken(string $source, int $schemaCookie, string $pattern, string $escape, string $lastKey, int $lastRowid, int $pageSize, array $page): array
    {
        $tail = $page === [] ? null : $page[array_key_last($page)];

        return [
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'patternHash' => substr(hash('sha256', $pattern), 0, 16),
            'escapeHash' => substr(hash('sha256', $escape), 0, 16),
            'lastKeyHash' => substr(hash('sha256', $lastKey), 0, 16),
            'lastKey' => $lastKey,
            'lastRowid' => $lastRowid,
            'pageSize' => $pageSize,
            'pageRowids' => self::rowids($page),
            'tailRowid' => is_array($tail) ? $tail['rowid'] : null,
            'tailKey' => is_array($tail) ? $tail['nocaseKey'] : null,
        ];
    }

    /** @param array<string,mixed> $cursor @param array<string,mixed> $token */
    private static function assertResumeToken(array $cursor, array $token): void
    {
        foreach (['source', 'schemaCookie', 'patternHash', 'escapeHash', 'lastKeyHash', 'lastKey', 'lastRowid', 'pageSize', 'tailRowid', 'tailKey'] as $key) {
            if (($cursor[$key] ?? null) !== $token[$key]) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next224 resume token does not match current source keyset page');
            }
        }
        if (($cursor['pageRowids'] ?? null) !== $token['pageRowids']) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next224 resume token does not match current source keyset page');
        }
    }
}
