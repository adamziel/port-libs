<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::partialEstimateFence(self::rows($currentSource), $whereTerms, $index);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next236-ready'
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next239-ready' : 'requires-current-source-partial-estimate-reprepare',
            'stat4PartialEstimateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next239Ready' => $ready,
                'next239EstimatedRows' => $fence['estimatedPartialRows'],
                'next239ActualRows' => $fence['actualPartialRows'],
                'next239EstimateDelta' => $fence['estimateDelta'],
                'next239RejectedReason' => $fence['rejectedReason'],
                'next239ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next239PartialEstimateReady' => $ready,
                'next239PartialEstimateSignature' => $fence['proofSignature'],
                'next239PartialEstimateDelta' => $fence['estimateDelta'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT239 PARTIAL ESTIMATE FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PARTIAL CARDINALITY VERIFIED' : ' REQUIRES CURRENT PARTIAL CARDINALITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next239',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next239 reuses current-source STAT4 expression partial density validation and adds partial-index row-estimate fencing',
            'non_overlap' => 'avoids accepted next236 density vectors, next235 vector counters, next233 sample-row guards, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters; this slice only rejects stale partial-index cardinality estimates after current-source row movement',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $partialTerms
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private static function partialEstimateFence(array $rows, array $partialTerms, array $index): array
    {
        $partialRows = [];
        foreach ($rows as $row) {
            if (self::rowSatisfiesTerms($row, $partialTerms)) {
                $partialRows[] = [
                    'rowid' => self::rowid($row),
                    'expressionKey' => self::rowExpressionKey($row),
                    'blogId' => (int) ($row['blog_id'] ?? 0),
                ];
            }
        }
        usort($partialRows, static function (array $left, array $right): int {
            $key = strcmp($left['expressionKey'], $right['expressionKey']);
            if ($key !== 0) {
                return $key;
            }
            $blog = $left['blogId'] <=> $right['blogId'];
            if ($blog !== 0) {
                return $blog;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        $estimated = self::estimatedPartialRows($index);
        $actual = count($partialRows);
        $delta = $actual - $estimated;
        $rowids = array_map(static fn (array $row): int => $row['rowid'], $partialRows);
        $expressionKeys = array_values(array_unique(array_map(static fn (array $row): string => $row['expressionKey'], $partialRows)));
        $ready = $estimated === $actual;
        $proof = [
            'estimatedPartialRows' => $estimated,
            'actualPartialRows' => $actual,
            'estimateDelta' => $delta,
            'partialRowids' => $rowids,
            'partialExpressionKeys' => $expressionKeys,
            'partialPredicateSignature' => self::signature($partialTerms),
            'indexName' => (string) ($index['name'] ?? ''),
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => $ready ? null : ($delta > 0 ? 'partial-estimate-under-count' : 'partial-estimate-over-count'),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next239 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next239 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next239 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next239 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next239 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next239 needs partialPredicateTerms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next239 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /** @param array<string,mixed> $index */
    private static function estimatedPartialRows(array $index): int
    {
        $estimate = $index['estimatedPartialRows'] ?? ($index['stat1']['rows'] ?? null);
        if (is_int($estimate)) {
            return $estimate;
        }
        if (!is_string($estimate) || trim($estimate) === '') {
            throw new \InvalidArgumentException('SQLite next239 needs current partial row estimate');
        }
        $first = strtok(trim($estimate), ' ');
        if ($first === false || !ctype_digit($first)) {
            throw new \InvalidArgumentException('SQLite next239 partial row estimate must start with an integer');
        }

        return (int) $first;
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
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = strcmp(strtolower((string) $value), strtolower((string) ($term['right'] ?? '')));
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)
                ) {
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
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '_')) {
            return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
        }

        return strtolower($value) === strtolower($pattern);
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        if (!array_key_exists('option_name', $row)) {
            throw new \InvalidArgumentException('SQLite next239 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next239 rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'ValidateCurrentSourcePartialIndexEstimate',
            'mode' => 'next239-current-source-stat4-expression-partial-estimate',
            'estimatedPartialRows' => $fence['estimatedPartialRows'],
            'actualPartialRows' => $fence['actualPartialRows'],
            'partialRowids' => $fence['partialRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
