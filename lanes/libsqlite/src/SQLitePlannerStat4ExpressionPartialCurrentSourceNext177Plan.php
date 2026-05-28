<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext177Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        $between = self::betweenTerm($whereTerms);
        $normalizedTerms = self::normalizeBetweenTerms($whereTerms);
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan::materialize(
            $preparedSource,
            $currentSource,
            $normalizedTerms,
            $neededColumns,
        );
        $selected = self::arrayValue($base, 'selectedPlan');
        $range = self::arrayValue($selected, 'rangeConstraint');
        $matchedRows = self::listValue($base, 'matchedRows');
        $lowerRowids = self::boundaryRowids($matchedRows, $range['lower'] ?? null);
        $upperRowids = self::boundaryRowids($matchedRows, $range['upper'] ?? null);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next164-ready'
            && $between !== null
            && ($range['lowerInclusive'] ?? false) === true
            && ($range['upperInclusive'] ?? false) === true
            && $lowerRowids !== []
            && $upperRowids !== [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next177-ready' : 'requires-current-source-reprepare',
            'betweenTerm' => $between,
            'normalizedWhereTerms' => $normalizedTerms,
            'betweenFence' => [
                'expression' => $between['expression'] ?? null,
                'lower' => $range['lower'] ?? null,
                'upper' => $range['upper'] ?? null,
                'lowerInclusive' => $range['lowerInclusive'] ?? null,
                'upperInclusive' => $range['upperInclusive'] ?? null,
                'lowerBoundaryRowids' => $lowerRowids,
                'upperBoundaryRowids' => $upperRowids,
                'matchedBoundaryRowids' => array_values(array_unique(array_merge($lowerRowids, $upperRowids))),
                'betweenSignature' => self::signature($between),
                'normalizedTermSignature' => self::signature($normalizedTerms),
            ],
            'cursorProgram' => self::cursorProgram(self::listValue($base, 'cursorProgram'), $ready),
            'selectedPlan' => array_replace($selected, [
                'next177Ready' => $ready,
                'next177BetweenInclusive' => ($range['lowerInclusive'] ?? false) === true && ($range['upperInclusive'] ?? false) === true,
                'next177LowerBoundaryRowids' => $lowerRowids,
                'next177UpperBoundaryRowids' => $upperRowids,
                'next177NormalizedOperator' => 'BETWEEN',
            ]),
            'stat4Fence' => array_replace(self::arrayValue($base, 'stat4Fence'), [
                'next177BetweenSignature' => self::signature($between),
                'next177BoundarySignature' => self::signature([$lowerRowids, $upperRowids]),
            ]),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT177 BETWEEN '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ($ready ? ' INCLUSIVE BOUNDARIES' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next177',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next177 reuses current-source STAT4 expression partial planning and adds BETWEEN inclusive-bound normalization/fencing',
            'non_overlap' => 'avoids accepted next164 explicit range terms, next169 full-vs-partial cost fencing, next170 next-source row churn admission, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters; this slice only normalizes expression BETWEEN terms into inclusive current-source STAT4 partial-index bounds',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array<string,mixed>|null
     */
    private static function betweenTerm(array $terms): ?array
    {
        foreach ($terms as $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'BETWEEN') {
                continue;
            }
            $left = $term['left'] ?? null;
            if (!is_array($left) || !is_string($left['expression'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next177 BETWEEN term needs expression left side');
            }
            $bounds = self::betweenBounds($term);

            return [
                'expression' => self::normalizeExpression($left['expression']),
                'lower' => $bounds[0],
                'upper' => $bounds[1],
            ];
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array<string,mixed>>
     */
    private static function normalizeBetweenTerms(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'BETWEEN') {
                $out[] = $term;
                continue;
            }
            $left = $term['left'] ?? null;
            if (!is_array($left) || !is_string($left['expression'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next177 BETWEEN term needs expression left side');
            }
            $bounds = self::betweenBounds($term);
            $out[] = ['left' => $left, 'operator' => '>=', 'right' => $bounds[0]];
            $out[] = ['left' => $left, 'operator' => '<=', 'right' => $bounds[1]];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $term
     * @return array{0:mixed,1:mixed}
     */
    private static function betweenBounds(array $term): array
    {
        if (array_key_exists('lower', $term) && array_key_exists('upper', $term)) {
            return [$term['lower'], $term['upper']];
        }
        $values = $term['values'] ?? null;
        if (is_array($values) && array_is_list($values) && count($values) === 2) {
            return [$values[0], $values[1]];
        }

        throw new \InvalidArgumentException('SQLite next177 BETWEEN term needs lower and upper bounds');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function boundaryRowids(array $rows, mixed $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next177 matched rows must be arrays');
            }
            if (($row['expressionKey'] ?? null) === $key) {
                $out[] = (int) ($row['rowid'] ?? 0);
            }
        }
        sort($out);

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function arrayValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next177 needs array ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next177 needs list ' . $key);
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready): array
    {
        if (!$ready) {
            return $program;
        }
        if (isset($program[2]) && is_array($program[2]) && ($program[2]['opcode'] ?? null) === 'IdxLT') {
            $program[2]['opcode'] = 'IdxLE';
        }
        array_splice($program, 3, 0, [[
            'opcode' => 'BetweenInclusiveFence',
            'mode' => 'next177-current-source-stat4-expression-partial',
        ]]);

        return $program;
    }

    private static function normalizeExpression(string $expression): string
    {
        $expression = strtolower(trim($expression));
        $expression = (string) preg_replace('/\s+/', '', $expression);
        $expression = str_replace('(option_name)', '(option_name)', $expression);

        return $expression;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
