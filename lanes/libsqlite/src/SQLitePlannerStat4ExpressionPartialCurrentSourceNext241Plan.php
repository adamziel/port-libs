<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $fence = self::residualWhereFence(
            self::rowsByRowid($currentSource),
            self::rowids($base['matchedRowids'] ?? null, 'matched rowids'),
            $whereTerms,
        );
        $payloadReady = is_array($base['stat4CoveringPayloadFence'] ?? null)
            && ($base['stat4CoveringPayloadFence']['allPayloadsMatchCurrentRows'] ?? false) === true
            && ($base['stat4CoveringPayloadFence']['payloadMismatchRowids'] ?? null) === []
            && ($base['stat4CoveringPayloadFence']['missingPayloadRowids'] ?? null) === []
            && ($base['stat4CoveringPayloadFence']['stalePayloadRowids'] ?? null) === [];
        $ready = $payloadReady
            && $fence['allMatchedRowsSatisfyResidualWhere'] === true
            && $fence['residualRejectedRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next241-ready' : 'requires-current-source-stat4-residual-reprepare',
            'stat4ResidualWhereFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next241Ready' => $ready,
                'next241ResidualWhereSignature' => $fence['residualWhereSignature'],
                'next241ProofSignature' => $fence['proofSignature'],
                'next241ResidualRejectedRowids' => $fence['residualRejectedRowids'],
                'next241ResidualAcceptedRowids' => $fence['residualAcceptedRowids'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next241ResidualWhereSignature' => $fence['residualWhereSignature'],
                'next241ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT241 RESIDUAL WHERE FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT RESIDUALS VERIFIED' : ' REQUIRES CURRENT RESIDUAL REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next241',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next241 reuses current-source STAT4 expression partial covering payload fences and adds residual WHERE validation for yielded rowids',
            'non_overlap' => 'adds current residual WHERE validation after accepted next238 covering payload staleness checks; avoids next238 payload validation, next234 histogram validation, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next241 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next241 current rows must be arrays');
            }
            $rowid = self::intValue($row['rowid'] ?? null, 'current rowid');
            if (isset($out[$rowid])) {
                throw new \InvalidArgumentException('SQLite next241 duplicate current rowid');
            }
            $out[$rowid] = $row;
        }

        return $out;
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next241 needs ' . $label);
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, $label), $value));
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<int> $matchedRowids
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,mixed>
     */
    private static function residualWhereFence(array $rowsByRowid, array $matchedRowids, array $whereTerms): array
    {
        if ($whereTerms === []) {
            throw new \InvalidArgumentException('SQLite next241 needs residual where terms');
        }
        $proofs = [];
        $accepted = [];
        $rejected = [];
        foreach ($matchedRowids as $rowid) {
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                throw new \InvalidArgumentException('SQLite next241 matched rowid missing from current source');
            }
            $termProofs = [];
            $ok = true;
            foreach ($whereTerms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next241 where terms must be arrays');
                }
                $operator = strtoupper((string) ($term['operator'] ?? ''));
                $value = self::leftValue($term['left'] ?? null, $row);
                $matches = self::termSatisfied($operator, $value, $term);
                $ok = $ok && $matches;
                $termProofs[] = [
                    'operator' => $operator,
                    'left' => self::leftLabel($term['left'] ?? null),
                    'value' => $value,
                    'right' => $term['right'] ?? null,
                    'lower' => $term['lower'] ?? null,
                    'upper' => $term['upper'] ?? null,
                    'matches' => $matches,
                ];
            }
            if ($ok) {
                $accepted[] = $rowid;
            } else {
                $rejected[] = $rowid;
            }
            $proofs[] = [
                'rowid' => $rowid,
                'expressionKey' => strtolower((string) ($row['option_name'] ?? '')),
                'residualMatches' => $ok,
                'termProofs' => $termProofs,
            ];
        }

        return [
            'matchedRowCount' => count($matchedRowids),
            'residualTermCount' => count($whereTerms),
            'residualAcceptedRowids' => $accepted,
            'residualRejectedRowids' => $rejected,
            'rowProofs' => $proofs,
            'allMatchedRowsSatisfyResidualWhere' => $rejected === [],
            'residualWhereSignature' => self::signature($whereTerms),
            'proofSignature' => self::signature([$matchedRowids, $proofs, $accepted, $rejected]),
        ];
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next241 where term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower(preg_replace('/\s+/', '', (string) ($left['expression'] ?? '')) ?? '');
        if ($expression === 'lower(option_name)') {
            return strtolower((string) ($row['option_name'] ?? ''));
        }

        throw new \InvalidArgumentException('SQLite next241 unsupported residual expression');
    }

    private static function leftLabel(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $left['column'];
        }

        return strtolower(preg_replace('/\s+/', '', (string) ($left['expression'] ?? '')) ?? '');
    }

    /** @param array<string,mixed> $term */
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
            default => throw new \InvalidArgumentException('SQLite next241 unsupported residual operator ' . $operator),
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

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next241 ' . $label . ' must be an integer');
        }

        return (int) $value;
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
            'opcode' => 'VerifyCurrentStat4ResidualWhere',
            'mode' => 'next241-current-source-stat4-expression-partial-residual-where',
            'matchedRowCount' => $fence['matchedRowCount'],
            'residualTermCount' => $fence['residualTermCount'],
            'acceptedRowids' => $fence['residualAcceptedRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
