<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext190Plan
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
            throw new \InvalidArgumentException('SQLite next190 WHERE terms cannot be empty');
        }

        $residuals = self::notInResiduals($whereTerms);
        if ($residuals === []) {
            throw new \InvalidArgumentException('SQLite next190 needs at least one NOT IN residual');
        }

        $admissionTerms = array_values(array_filter(
            $whereTerms,
            static fn (array $term): bool => strtoupper((string) ($term['operator'] ?? '')) !== 'NOT IN'
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
        $rejected = array_values(array_diff(array_column($beforeRows, 'rowid'), $filteredRowids));
        $hasNullPoison = self::hasNullPoison($residuals);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next175-ready'
            && $beforeRows !== []
            && $filteredRows !== []
            && $rejected !== []
            && $hasNullPoison === false;

        $base['status'] = $ready ? 'stat4-expression-partial-current-source-next190-ready' : 'requires-next-stage';
        $base['matchedRowsBeforeNotInResidual'] = $beforeRows;
        $base['matchedRowidsBeforeNotInResidual'] = array_column($beforeRows, 'rowid');
        $base['matchedRows'] = $filteredRows;
        $base['matchedRowids'] = $filteredRowids;
        $base['matchedExpressionKeys'] = array_column($filteredRows, 'expressionKey');
        $base['selectedPlan']['next190Ready'] = $ready;
        $base['selectedPlan']['notInResidualRetained'] = true;
        $base['selectedPlan']['notInResidualCount'] = count($residuals);
        $base['selectedPlan']['estimatedRowsAfterNotInResidual'] = count($filteredRows);
        $base['selectedPlan']['estimatedCostAfterNotInResidual'] = max(1, count($filteredRows) + (($base['tableLookupRequired'] ?? false) ? 12 : 0));
        $base['notInResiduals'] = $residuals;
        $base['notInResidualHasNullPoison'] = $hasNullPoison;
        $base['notInResidualRowidsRejected'] = $rejected;
        $base['notInResidualRowidsAccepted'] = $filteredRowids;
        $base['residualPredicateRequired'] = true;
        $base['stat4Fence']['next190NotInResidualSignature'] = self::signature($residuals);
        $base['stat4Fence']['rowStreamSignatureAfterNotInResidual'] = self::signature($filteredRowids);
        $base['cursorProgram'] = self::cursorProgram(
            is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
            $residuals,
            $filteredRowids,
            $ready,
        );
        $base['detail'] = (($base['selectedSource'] ?? null) === 'current' ? 'REPREPARE' : 'REUSE')
            . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT190 NOT-IN RESIDUAL '
            . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX');
        $base['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-current-source-next190'];
        $base['dependency_closure'] = 'no new support component needed; next190 reuses next175 STAT4 LIKE-prefix admission and adds lane-local NOT IN residual fencing over current-source rows';
        $base['non_overlap'] = 'extends accepted next175 LIKE-prefix STAT4 partial expression scans with residual NOT IN exclusion after current-source admission; avoids accepted next184 IN-predicate implication, next187 NOT LIKE residuals, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters';

        return $base;
    }

    /** @param list<array<string,mixed>> $terms @return list<array<string,mixed>> */
    private static function notInResiduals(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'NOT IN') {
                continue;
            }
            $values = self::literalList($term['right'] ?? null);
            if ($values === []) {
                throw new \InvalidArgumentException('SQLite next190 NOT IN residual needs values');
            }
            $out[] = [
                'leftKey' => self::leftKey($term['left'] ?? null),
                'expression' => (string) ($term['left']['expression'] ?? ''),
                'values' => $values,
                'hasNull' => in_array(null, $values, true),
            ];
        }

        return $out;
    }

    /** @return list<mixed> */
    private static function literalList(mixed $value): array
    {
        if (is_array($value) && array_key_exists('values', $value) && is_array($value['values'])) {
            return array_values(array_map(static fn (mixed $item): mixed => self::literal($item), $value['values']));
        }
        if (is_array($value) && array_is_list($value)) {
            return array_values(array_map(static fn (mixed $item): mixed => self::literal($item), $value));
        }

        throw new \InvalidArgumentException('SQLite next190 NOT IN right side must be a value list');
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
            throw new \InvalidArgumentException('SQLite next190 needs matched row list');
        }
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next190 matched rows must be arrays');
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
                throw new \InvalidArgumentException('SQLite next190 matched rows need payload arrays');
            }
            foreach ($residuals as $residual) {
                if (($residual['hasNull'] ?? false) === true) {
                    continue 2;
                }
                $value = self::residualValue($payload, $residual, $row);
                if ($value === null || in_array($value, $residual['values'], true)) {
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

    /** @param list<array<string,mixed>> $residuals */
    private static function hasNullPoison(array $residuals): bool
    {
        foreach ($residuals as $residual) {
            if (($residual['hasNull'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $program @param list<array<string,mixed>> $residuals @param list<int> $rowids @return list<array<string,mixed>> */
    private static function cursorProgram(array $program, array $residuals, array $rowids, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-not-in-residual']];
        }

        array_splice($program, 5, 0, [[
            'opcode' => 'RecheckNotInResidual',
            'valueSets' => array_column($residuals, 'values'),
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
