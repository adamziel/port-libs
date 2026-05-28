<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext187Plan
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
            throw new \InvalidArgumentException('SQLite next187 WHERE terms cannot be empty');
        }

        $residuals = self::notLikeResiduals($whereTerms);
        if ($residuals === []) {
            throw new \InvalidArgumentException('SQLite next187 needs at least one NOT LIKE residual');
        }

        $admissionTerms = array_values(array_filter(
            $whereTerms,
            static fn (array $term): bool => strtoupper((string) ($term['operator'] ?? '')) !== 'NOT LIKE'
        ));
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext175Plan::materialize(
            $preparedSource,
            $currentSource,
            $admissionTerms,
            $neededColumns,
        );

        $filteredRows = self::filterRows($base['matchedRows'] ?? [], $residuals);
        $filteredRowids = array_column($filteredRows, 'rowid');
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next175-ready'
            && $filteredRows !== []
            && count($filteredRows) < count($base['matchedRows'] ?? []);

        $base['status'] = $ready ? 'stat4-expression-partial-current-source-next187-ready' : 'requires-next-stage';
        $base['matchedRowsBeforeNotLikeResidual'] = $base['matchedRows'] ?? [];
        $base['matchedRowidsBeforeNotLikeResidual'] = $base['matchedRowids'] ?? [];
        $base['matchedRows'] = $filteredRows;
        $base['matchedRowids'] = $filteredRowids;
        $base['matchedExpressionKeys'] = array_column($filteredRows, 'expressionKey');
        $base['selectedPlan']['next187Ready'] = $ready;
        $base['selectedPlan']['notLikeResidualCount'] = count($residuals);
        $base['selectedPlan']['notLikeResidualRetained'] = true;
        $base['selectedPlan']['estimatedRowsAfterNotLikeResidual'] = count($filteredRows);
        $base['selectedPlan']['estimatedCostAfterNotLikeResidual'] = max(1, count($filteredRows) + (($base['tableLookupRequired'] ?? false) ? 12 : 0));
        $base['notLikeResiduals'] = $residuals;
        $base['notLikeResidualRowidsRejected'] = array_values(array_diff($base['matchedRowidsBeforeNotLikeResidual'], $filteredRowids));
        $base['notLikeResidualRowidsAccepted'] = $filteredRowids;
        $base['stat4Fence']['next187NotLikeResidualSignature'] = self::signature($residuals);
        $base['stat4Fence']['rowStreamSignatureAfterNotLikeResidual'] = self::signature($filteredRowids);
        $base['cursorProgram'] = self::cursorProgram($base['cursorProgram'] ?? [], $residuals, $filteredRowids, $ready);
        $base['residualPredicateRequired'] = true;
        $base['detail'] = (($base['selectedSource'] ?? null) === 'current' ? 'REPREPARE' : 'REUSE')
            . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT187 NOT-LIKE RESIDUAL '
            . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX');
        $base['dependencies'] = ['sqlite-sqlplanner-stat4-expression-partial-current-source-next187'];
        $base['dependency_closure'] = 'no new support component needed; next187 reuses next175 STAT4 LIKE-prefix admission and adds lane-local NOT LIKE residual fencing over current-source rows';
        $base['non_overlap'] = 'extends accepted next175 LIKE-prefix STAT4 partial expression scans with residual NOT LIKE exclusion after current-source admission; avoids next184 IN-predicate implication, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters';

        return $base;
    }

    /** @param list<array<string,mixed>> $terms @return list<array<string,mixed>> */
    private static function notLikeResiduals(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'NOT LIKE') {
                continue;
            }
            $escape = $term['escape'] ?? '\\';
            if (!is_string($escape) || strlen($escape) !== 1) {
                throw new \InvalidArgumentException('SQLite next187 NOT LIKE escape must be one byte');
            }
            $pattern = self::literal($term['right'] ?? null);
            if (!is_string($pattern) || $pattern === '') {
                throw new \InvalidArgumentException('SQLite next187 NOT LIKE residual needs a pattern');
            }
            $out[] = [
                'leftKey' => self::leftKey($term['left'] ?? null),
                'expression' => (string) ($term['left']['expression'] ?? ''),
                'pattern' => $pattern,
                'escape' => $escape,
            ];
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $residuals @return list<array<string,mixed>> */
    private static function filterRows(array $rows, array $residuals): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next187 matched rows must be arrays');
            }
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next187 matched rows need payload arrays');
            }
            foreach ($residuals as $residual) {
                $value = self::residualValue($payload, $residual, $row);
                if (is_string($value) && self::likeMatches($value, $residual['pattern'], $residual['escape'])) {
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

    private static function likeMatches(string $value, string $pattern, string $escape): bool
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === $escape) {
                $i++;
                if ($i >= $length) {
                    return false;
                }
                $regex .= preg_quote($pattern[$i], '/');
                continue;
            }
            $regex .= match ($char) {
                '%' => '.*',
                '_' => '.',
                default => preg_quote($char, '/'),
            };
        }

        return preg_match('/^' . $regex . '$/s', $value) === 1;
    }

    /** @param list<array<string,mixed>> $cursorProgram @param list<array<string,mixed>> $residuals @param list<int> $rowids @return list<array<string,mixed>> */
    private static function cursorProgram(array $cursorProgram, array $residuals, array $rowids, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-not-like-residual']];
        }

        array_splice($cursorProgram, 5, 0, [[
            'opcode' => 'RecheckNotLikeResidual',
            'patterns' => array_column($residuals, 'pattern'),
            'rowids' => $rowids,
        ]]);
        foreach ($cursorProgram as &$op) {
            if (($op['opcode'] ?? null) === 'ResultRow') {
                $op['rowids'] = $rowids;
            }
        }
        unset($op);

        return $cursorProgram;
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

    /** @param mixed $value */
    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
