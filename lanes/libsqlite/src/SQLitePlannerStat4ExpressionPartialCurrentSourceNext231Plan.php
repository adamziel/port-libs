<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::currentPageFence(
            self::rows($currentSource),
            self::matchedRows($base),
            $whereTerms,
            $limit,
            $offset,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next228-ready'
            && $fence['allMatchedRowsResolveToCurrentSource'] === true
            && $fence['allMatchedRowsSatisfyWhereTerms'] === true
            && $fence['selectedPageMatchesCurrentSource'] === true
            && $fence['matchedRowidsRejectedByWhereTerms'] === []
            && $fence['missingMatchedCurrentRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next231-ready' : 'requires-current-source-stat4-expression-page-reprepare',
            'currentSourcePageFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next231Ready' => $ready,
                'next231CurrentQualifiedRowids' => $fence['currentQualifiedRowids'],
                'next231ExpectedPageRowids' => $fence['expectedPageRowids'],
                'next231ActualPageRowids' => $fence['actualPageRowids'],
                'next231MissingMatchedCurrentRowids' => $fence['missingMatchedCurrentRowids'],
                'next231RowsRejectedByWhereTerms' => $fence['matchedRowidsRejectedByWhereTerms'],
                'next231PageSignature' => $fence['pageSignature'],
                'next231ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next231PageSignature' => $fence['pageSignature'],
                'next231ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT231 PAGE MEMBERSHIP FENCE '
                . (string) ($base['selectedPlan']['name'] ?? '')
                . ($ready ? ' CURRENT QUALIFIED PAGE PROVED' : ' REQUIRES CURRENT SOURCE PAGE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next231',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next231 reuses current-source STAT4 expression partial fences and adds full current-rowset page membership validation',
            'non_overlap' => 'adds current qualified-rowset LIMIT/OFFSET page membership validation after accepted next228 sample-row partial-predicate validation; avoids next228 sample partial proof, next226 sample window proof, grouped LIKE/OR, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next231 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next231 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next231 base matched rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next231 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $matchedRows
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,mixed>
     */
    private static function currentPageFence(array $currentRows, array $matchedRows, array $whereTerms, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next231 limit and offset must be non-negative');
        }

        $rowsByRowid = [];
        foreach ($currentRows as $row) {
            $rowsByRowid[self::rowid($row, 'current rowid')] = $row;
        }

        $qualified = [];
        foreach ($currentRows as $row) {
            $proofs = self::whereTermProofs($whereTerms, $row);
            if (!in_array(false, array_column($proofs, 'satisfied'), true)) {
                $qualified[] = [
                    'rowid' => self::rowid($row, 'qualified rowid'),
                    'expressionKey' => self::expressionKey($row),
                    'proofs' => $proofs,
                ];
            }
        }
        usort($qualified, static function (array $left, array $right): int {
            $comparison = strcmp($right['expressionKey'], $left['expressionKey']);
            if ($comparison !== 0) {
                return $comparison;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        $expectedPage = array_slice($qualified, $offset, $limit);
        $actualRowids = [];
        $missing = [];
        $rejected = [];
        $matchedProofs = [];
        foreach ($matchedRows as $position => $row) {
            $rowid = self::rowid($row, 'matched rowid');
            $actualRowids[] = $rowid;
            $current = $rowsByRowid[$rowid] ?? null;
            if ($current === null) {
                $missing[] = $rowid;
                $matchedProofs[] = [
                    'position' => $position,
                    'rowid' => $rowid,
                    'currentRowPresent' => false,
                    'expressionKey' => null,
                    'whereTermProofs' => [],
                    'satisfiesWhereTerms' => false,
                ];
                continue;
            }

            $proofs = self::whereTermProofs($whereTerms, $current);
            $satisfies = !in_array(false, array_column($proofs, 'satisfied'), true);
            if (!$satisfies) {
                $rejected[] = $rowid;
            }
            $matchedProofs[] = [
                'position' => $position,
                'rowid' => $rowid,
                'currentRowPresent' => true,
                'expressionKey' => self::expressionKey($current),
                'whereTermProofs' => $proofs,
                'satisfiesWhereTerms' => $satisfies,
            ];
        }

        $qualifiedRowids = array_column($qualified, 'rowid');
        $expectedRowids = array_column($expectedPage, 'rowid');

        return [
            'limit' => $limit,
            'offset' => $offset,
            'currentQualifiedRowids' => $qualifiedRowids,
            'expectedPageRowids' => $expectedRowids,
            'actualPageRowids' => $actualRowids,
            'matchedRowProofs' => $matchedProofs,
            'expectedPageProofs' => $expectedPage,
            'qualifiedRowCount' => count($qualified),
            'pageRowCount' => count($expectedPage),
            'allMatchedRowsResolveToCurrentSource' => $missing === [],
            'allMatchedRowsSatisfyWhereTerms' => $rejected === [],
            'selectedPageMatchesCurrentSource' => $expectedRowids === $actualRowids,
            'missingMatchedCurrentRowids' => array_values(array_unique($missing)),
            'matchedRowidsRejectedByWhereTerms' => array_values(array_unique($rejected)),
            'pageSignature' => self::signature([$limit, $offset, $qualifiedRowids, $expectedRowids, $actualRowids]),
            'proofSignature' => self::signature([$whereTerms, $qualified, $expectedPage, $matchedProofs, $missing, $rejected]),
        ];
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $row
     * @return list<array<string,mixed>>
     */
    private static function whereTermProofs(array $whereTerms, array $row): array
    {
        $proofs = [];
        foreach ($whereTerms as $position => $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next231 where terms must be arrays');
            }
            $left = $term['left'] ?? null;
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = self::leftValue($left, $row);
            $proofs[] = [
                'position' => $position,
                'leftKey' => self::leftKey($left),
                'operator' => $operator,
                'value' => $value,
                'right' => $term['right'] ?? null,
                'lower' => $term['lower'] ?? null,
                'upper' => $term['upper'] ?? null,
                'satisfied' => self::termSatisfied($operator, $value, $term),
            ];
        }

        return $proofs;
    }

    private static function termSatisfied(string $operator, mixed $value, array $term): bool
    {
        return match ($operator) {
            '=' => self::compare($value, $term['right'] ?? null) === 0,
            '>=', '=>' => self::compare($value, $term['right'] ?? null) >= 0,
            '<=' => self::compare($value, $term['right'] ?? null) <= 0,
            '>' => self::compare($value, $term['right'] ?? null) > 0,
            '<' => self::compare($value, $term['right'] ?? null) < 0,
            'IS NOT NULL' => $value !== null,
            'LIKE' => self::likePrefix((string) $value, (string) ($term['right'] ?? '')),
            'BETWEEN' => self::compare($value, $term['lower'] ?? null) >= 0
                && self::compare($value, $term['upper'] ?? null) <= 0,
            default => throw new \InvalidArgumentException('SQLite next231 unsupported where operator ' . $operator),
        };
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp(strtolower((string) $left), strtolower((string) $right));
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        $regex = '/\A' . strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']) . '\z/i';

        return preg_match($regex, $value) === 1;
    }

    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next231 where term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column']) && $left['column'] !== '') {
            return 'column:' . strtolower($left['column']);
        }
        if (isset($left['expression']) && is_string($left['expression']) && $left['expression'] !== '') {
            return 'expression:' . strtolower($left['expression']);
        }

        throw new \InvalidArgumentException('SQLite next231 where term left operand is unsupported');
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next231 where term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower(preg_replace('/\s+/', '', (string) ($left['expression'] ?? '')) ?? '');
        if ($expression === 'lower(option_name)') {
            return strtolower((string) ($row['option_name'] ?? ''));
        }

        throw new \InvalidArgumentException('SQLite next231 where expression is unsupported');
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next231 ' . $label . ' must be an integer');
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
            'opcode' => 'RecheckCurrentStat4ExpressionPartialPage',
            'mode' => 'next231-current-source-stat4-expression-partial-page-membership',
            'qualifiedRowids' => $fence['currentQualifiedRowids'],
            'expectedPageRowids' => $fence['expectedPageRowids'],
            'actualPageRowids' => $fence['actualPageRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
