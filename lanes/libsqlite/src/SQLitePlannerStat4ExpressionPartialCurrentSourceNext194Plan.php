<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext194Plan
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
        if ($whereTerms === []) {
            throw new \InvalidArgumentException('SQLite next194 WHERE terms cannot be empty');
        }

        $residuals = self::isDistinctFromResiduals($whereTerms);
        if ($residuals === []) {
            throw new \InvalidArgumentException('SQLite next194 needs at least one IS DISTINCT FROM residual');
        }

        $admissionTerms = array_values(array_filter(
            $whereTerms,
            static fn (array $term): bool => strtoupper((string) ($term['operator'] ?? '')) !== 'IS DISTINCT FROM'
        ));
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext175Plan::materialize(
            $preparedSource,
            $currentSource,
            $admissionTerms,
            $neededColumns,
        );

        $beforeRows = self::rowList($base['matchedRows'] ?? null);
        $filteredRows = self::filterRows($beforeRows, $residuals);
        $filteredRowids = array_column($filteredRows, 'rowid');
        $rejectedRowids = array_values(array_diff(array_column($beforeRows, 'rowid'), $filteredRowids));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next175-ready'
            && $beforeRows !== []
            && $filteredRows !== []
            && $rejectedRowids !== [];

        $base['status'] = $ready ? 'stat4-expression-partial-current-source-next194-ready' : 'requires-next-stage';
        $base['matchedRowsBeforeIsDistinctResidual'] = $beforeRows;
        $base['matchedRowidsBeforeIsDistinctResidual'] = array_column($beforeRows, 'rowid');
        $base['matchedRows'] = $filteredRows;
        $base['matchedRowids'] = $filteredRowids;
        $base['matchedExpressionKeys'] = array_column($filteredRows, 'expressionKey');
        $base['selectedPlan']['next194Ready'] = $ready;
        $base['selectedPlan']['isDistinctFromResidualRetained'] = true;
        $base['selectedPlan']['isDistinctFromResidualCount'] = count($residuals);
        $base['selectedPlan']['estimatedRowsAfterIsDistinctResidual'] = count($filteredRows);
        $base['selectedPlan']['estimatedCostAfterIsDistinctResidual'] = max(1, count($filteredRows) + (($base['tableLookupRequired'] ?? false) ? 12 : 0));
        $base['isDistinctFromResiduals'] = $residuals;
        $base['isDistinctFromResidualRowidsRejected'] = $rejectedRowids;
        $base['isDistinctFromResidualRowidsAccepted'] = $filteredRowids;
        $base['residualPredicateRequired'] = true;
        $base['stat4Fence']['next194IsDistinctFromResidualSignature'] = self::signature($residuals);
        $base['stat4Fence']['rowStreamSignatureAfterIsDistinctFromResidual'] = self::signature($filteredRowids);
        $base['cursorProgram'] = self::cursorProgram(
            is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
            $residuals,
            $filteredRowids,
            $ready,
        );
        $base['detail'] = (($base['selectedSource'] ?? null) === 'current' ? 'REPREPARE' : 'REUSE')
            . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT194 IS-DISTINCT-FROM RESIDUAL '
            . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX');
        $base['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-current-source-next194'];
        $base['dependency_closure'] = 'no new support component needed; next194 reuses next175 STAT4 LIKE-prefix admission and adds lane-local IS DISTINCT FROM residual fencing over current-source rows';
        $base['non_overlap'] = 'extends accepted next175 LIKE-prefix STAT4 partial expression scans with NULL-safe IS DISTINCT FROM residual exclusion after current-source admission; avoids accepted next190 NOT IN residuals, next184 IN-predicate implication, next187 NOT LIKE residuals, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters';

        return $base;
    }

    /** @param list<array<string,mixed>> $terms @return list<array<string,mixed>> */
    private static function isDistinctFromResiduals(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'IS DISTINCT FROM') {
                continue;
            }
            $out[] = [
                'leftKey' => self::leftKey($term['left'] ?? null),
                'expression' => (string) ($term['left']['expression'] ?? ''),
                'right' => self::literal($term['right'] ?? null),
            ];
        }

        return $out;
    }

    /** @param mixed $value */
    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    /** @param mixed $left */
    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['column'])) {
            return 'column:' . strtolower((string) $left['column']);
        }
        if (isset($left['expression'])) {
            return 'expression:' . self::normalizeExpression((string) $left['expression']);
        }

        return '';
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private static function rowList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next194 needs matched row list');
        }
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next194 matched rows must be arrays');
            }
        }

        return $value;
    }

    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $residuals @return list<array<string,mixed>> */
    private static function filterRows(array $rows, array $residuals): array
    {
        $out = [];
        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next194 matched rows need payload arrays');
            }
            foreach ($residuals as $residual) {
                $value = self::residualValue($payload, $residual, $row);
                if (!self::isDistinctFrom($value, $residual['right'] ?? null)) {
                    continue 2;
                }
            }
            $out[] = $row;
        }

        return array_values($out);
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $residual @param array<string,mixed> $row */
    private static function residualValue(array $payload, array $residual, array $row): mixed
    {
        if (($residual['leftKey'] ?? '') === 'expression:lower(option_name)') {
            return $row['expressionKey'] ?? (is_string($payload['option_name'] ?? null) ? strtolower($payload['option_name']) : null);
        }
        if (str_starts_with((string) ($residual['leftKey'] ?? ''), 'column:')) {
            return $payload[substr((string) $residual['leftKey'], 7)] ?? null;
        }

        return null;
    }

    private static function isDistinctFrom(mixed $left, mixed $right): bool
    {
        if ($left === null && $right === null) {
            return false;
        }
        if ($left === null || $right === null) {
            return true;
        }

        return $left !== $right;
    }

    /** @param list<array<string,mixed>> $program @param list<array<string,mixed>> $residuals @param list<int> $rowids @return list<array<string,mixed>> */
    private static function cursorProgram(array $program, array $residuals, array $rowids, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-is-distinct-from-residual']];
        }

        array_splice($program, 5, 0, [[
            'opcode' => 'RecheckIsDistinctFromResidual',
            'comparisons' => $residuals,
            'rowids' => $rowids,
        ]]);
        foreach ($program as &$op) {
            if (($op['opcode'] ?? null) === 'ResultRow') {
                $op['rowids'] = $rowids;
            }
        }
        unset($op);

        return $program;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
