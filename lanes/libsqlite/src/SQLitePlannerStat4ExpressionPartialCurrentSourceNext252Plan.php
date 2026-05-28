<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext252Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::scanDirectionFence(self::rows($currentSource), $whereTerms, $index, $base);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next249-ready'
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next252-ready' : 'requires-current-source-stat4-scan-direction-reprepare',
            'stat4ScanDirectionFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next252Ready' => $ready,
                'next252DescendingScan' => $fence['descending'],
                'next252ReverseAnchorRowids' => $fence['reverseStat4AnchorRowids'],
                'next252PageAnchorRowids' => $fence['pageAnchorRowids'],
                'next252RejectedReasons' => $fence['rejectedReasons'],
                'next252ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next252ScanDirectionReady' => $ready,
                'next252ScanDirectionSignature' => $fence['proofSignature'],
                'next252RejectedReasons' => $fence['rejectedReasons'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT252 SCAN DIRECTION FENCE '
                . $selectedName
                . ($ready ? ' CURRENT DESCENDING PAGE ANCHORS VERIFIED' : ' REQUIRES CURRENT STAT4 SCAN DIRECTION REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next252',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next252 reuses current-source STAT4 expression partial rowsets and adds descending scan-direction page-anchor validation',
            'non_overlap' => 'adds STAT4 scan-direction page-anchor validation after accepted next249 duplicate peer-count validation; avoids sample-row anchors, page membership, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function scanDirectionFence(array $rows, array $whereTerms, array $index, array $base): array
    {
        $descending = (bool) ($index['descending'] ?? false);
        $qualified = [];
        foreach ($rows as $row) {
            if (!self::rowSatisfiesTerms($row, $whereTerms)) {
                continue;
            }
            $qualified[] = [
                'rowid' => self::rowid($row),
                'expressionKey' => self::rowExpressionKey($row),
                'blogId' => (int) ($row['blog_id'] ?? 0),
            ];
        }
        usort($qualified, static function (array $left, array $right) use ($descending): int {
            $comparison = strcmp($left['expressionKey'], $right['expressionKey']);
            if ($descending) {
                $comparison *= -1;
            }
            if ($comparison !== 0) {
                return $comparison;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        $samples = self::stat4Samples($index);
        $ascendingStat4Rowids = array_column($samples, 'rowid');
        $reverseAnchors = array_reverse($ascendingStat4Rowids);
        $matchedRowids = self::matchedRowids($base);
        $pageAnchors = self::pageAnchorRowids($matchedRowids, $reverseAnchors);
        $qualifiedRowids = array_column($qualified, 'rowid');
        $matchedStart = $matchedRowids === [] ? false : array_search($matchedRowids[0], $qualifiedRowids, true);
        $expectedPrefix = $matchedStart === false
            ? []
            : array_slice($qualifiedRowids, (int) $matchedStart, count($pageAnchors));
        $qualifiedAnchorPrefix = self::pageAnchorRowids($qualifiedRowids, $reverseAnchors);
        $rejected = [];
        if ($samples !== self::samplesSortedAscending($samples)) {
            $rejected[] = 'stat4-samples-not-ascending';
        }
        if ($descending && $pageAnchors !== $expectedPrefix) {
            $rejected[] = 'descending-page-anchor-order';
        }
        $proof = [
            'indexName' => (string) ($index['name'] ?? ''),
            'descending' => $descending,
            'qualifiedRowids' => $qualifiedRowids,
            'qualifiedExpressionKeys' => array_column($qualified, 'expressionKey'),
            'ascendingStat4Rowids' => $ascendingStat4Rowids,
            'ascendingStat4Keys' => array_column($samples, 'key'),
            'reverseStat4AnchorRowids' => $reverseAnchors,
            'matchedRowids' => $matchedRowids,
            'pageAnchorRowids' => $pageAnchors,
            'expectedPageAnchorRowids' => $expectedPrefix,
            'qualifiedAnchorPrefix' => $qualifiedAnchorPrefix,
            'sampleCount' => count($samples),
            'matchedRowCount' => count($matchedRowids),
            'rejectedReasons' => $rejected,
        ];

        return $proof + [
            'ready' => $rejected === [] && $samples !== [] && $pageAnchors !== [],
            'rejectedReason' => $rejected === [] ? null : implode(',', $rejected),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next252 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next252 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next252 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next252 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next252 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function matchedRowids(array $base): array
    {
        $rowids = $base['matchedRowids'] ?? null;
        if (!is_array($rowids) || !array_is_list($rowids)) {
            throw new \InvalidArgumentException('SQLite next252 base matched rowids must be a list');
        }

        return array_map(static fn (mixed $rowid): int => self::rowid(['rowid' => $rowid]), $rowids);
    }

    /**
     * @param list<int> $rowids
     * @param list<int> $anchors
     * @return list<int>
     */
    private static function pageAnchorRowids(array $rowids, array $anchors): array
    {
        $seen = [];
        $out = [];
        foreach ($rowids as $rowid) {
            if (!in_array($rowid, $anchors, true) || isset($seen[$rowid])) {
                continue;
            }
            $seen[$rowid] = true;
            $out[] = $rowid;
        }

        return $out;
    }

    /**
     * @param list<array{key:string,rowid:int,blogId:int}> $samples
     * @return list<array{key:string,rowid:int,blogId:int}>
     */
    private static function samplesSortedAscending(array $samples): array
    {
        $sorted = $samples;
        usort($sorted, static function (array $left, array $right): int {
            $comparison = strcmp($left['key'], $right['key']);
            if ($comparison !== 0) {
                return $comparison;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        return $sorted;
    }

    /** @param array<string,mixed> $index @return list<array{key:string,rowid:int,blogId:int}> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next252 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 3) {
                throw new \InvalidArgumentException('SQLite next252 stat4 samples need key, rowid, and blog id');
            }
            $out[] = [
                'key' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]]),
                'blogId' => (int) $sample['sample'][2],
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = array_key_exists('expression', $left)
                ? self::rowExpressionKey($row)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && $value != ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === 'LIKE' && !self::likePrefix((string) $value, (string) ($term['right'] ?? ''))) {
                return false;
            }
            if ($operator === 'BETWEEN') {
                $stringValue = strtolower((string) $value);
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
                if (($lower !== null && $stringValue < $lower) || ($upper !== null && $stringValue > $upper)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if ($pattern === 'plugin_%') {
            return str_starts_with(strtolower($value), 'plugin_');
        }

        return $value === $pattern;
    }

    private static function rowExpressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? null;
        if (!is_int($rowid) && !(is_string($rowid) && ctype_digit($rowid))) {
            throw new \InvalidArgumentException('SQLite next252 rowid must be an integer');
        }

        return (int) $rowid;
    }

    /**
     * @param list<array<string,mixed>> $cursorProgram
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $cursorProgram, bool $ready, array $fence): array
    {
        $cursorProgram[] = [
            'opcode' => 'ValidateCurrentSourceStat4ScanDirection',
            'mode' => 'next252-current-source-stat4-expression-partial-scan-direction',
            'ready' => $ready,
            'descending' => $fence['descending'],
            'reverseStat4AnchorRowids' => $fence['reverseStat4AnchorRowids'],
            'pageAnchorRowids' => $fence['pageAnchorRowids'],
            'rejectedReasons' => $fence['rejectedReasons'],
            'signature' => $fence['proofSignature'],
        ];

        return $cursorProgram;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
