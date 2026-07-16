<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteExpressionPartialCoveringCurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns
    ): array {
        self::validateQueryTerms($queryTerms);
        self::validateNeededColumns($neededColumns);

        $prepared = self::sourcePlan($preparedSource, $queryTerms, $neededColumns);
        $current = self::sourcePlan($currentSource, $queryTerms, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $rows = self::coveredRows($source, $selected, $queryTerms, $neededColumns);
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['covering'] ?? false) === true
            && ($selected['expressionMatched'] ?? false) === true
            && $rows !== [];

        return [
            'status' => $ready ? 'expression-partial-covering-current-source-next148-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $current, $currentSignature),
            'selectedPlan' => $selected + [
                'coveredRowCount' => count($rows),
                'coveredRowids' => array_column($rows, 'rowid'),
                'currentNextSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
            ],
            'coveredRows' => $rows,
            'currentNextRows' => self::currentNextRows($rows),
            'coveringPayloadColumns' => array_values($neededColumns),
            'tableLookupElided' => $ready,
            'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
            'cursorProgram' => self::cursorProgram($selected, $rows, $neededColumns, $ready),
            'currentSourceFence' => [
                'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
                'indexSignature' => $stale ? $currentSignature : $preparedSignature,
                'expressionSignature' => self::normalizedExpression((string) ($selected['expression'] ?? '')),
                'partialPredicateSignature' => hash('sha256', json_encode($selected['partialPredicateTerms'] ?? [], JSON_THROW_ON_ERROR)),
                'coveringSignature' => implode(',', array_map('strtolower', $neededColumns)),
                'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' EXPRESSION PARTIAL COVERING CURRENT SOURCE NEXT148 '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ($ready ? ' WITHOUT TABLE SEEK' : ' FALLBACK TABLE SEEK'),
            'dependencies' => [
                'SQLiteCreateIndex expression/partial metadata',
                'sqlite-sqlplanner-expression-partial-covering-current-source-next148',
            ],
            'dependency_closure' => 'no new support component needed; next148 reuses lane-local expression metadata, partial predicate implication, and covering cursor diagnostics',
            'non_overlap' => 'avoids accepted expression partial skip-scan, STAT4 partial covering, partial range covering, expression ORDER BY, expression-index range costs, JSON, VFS/WAL, and B-tree clusters; this slice covers non-skip-scan expression partial covering current-source selection',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $queryTerms, array $neededColumns): array
    {
        $best = null;
        foreach (self::indexes($source) as $index) {
            $expression = (string) ($index['expression'] ?? '');
            $plan = [
                'name' => (string) ($index['name'] ?? ''),
                'rootPage' => self::arrayInt($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'partialPredicateTerms' => self::list($index['partialPredicateTerms'] ?? []),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'estimatedRows' => self::arrayInt($index, 'estimatedRows', 0),
                'stat4Samples' => self::list($index['stat4Samples'] ?? []),
            ];
            $expressionTerm = self::matchingExpressionTerm($queryTerms, $expression);
            $plan['expressionMatched'] = $expressionTerm !== null;
            $plan['expressionOperator'] = $expressionTerm['operator'] ?? null;
            $plan['expressionValue'] = $expressionTerm['right'] ?? null;
            $plan['partialPredicateImplied'] = self::partialPredicateImplied($plan['partialPredicateTerms'], $queryTerms);
            $plan['covering'] = count(array_diff(array_map('strtolower', $neededColumns), array_map('strtolower', $plan['coveringColumns']))) === 0;
            $plan['usable'] = $plan['expressionMatched'] === true && $plan['partialPredicateImplied'] === true;
            $plan['usesSkipScan'] = false;
            $plan['detail'] = $plan['usable']
                ? 'SEARCH ' . $plan['name'] . ' USING EXPRESSION PARTIAL COVERING'
                : 'SCAN TABLE; EXPRESSION PARTIAL COVERING UNUSABLE';

            if ($best === null || ((int) $plan['estimatedRows'] < (int) ($best['estimatedRows'] ?? PHP_INT_MAX))) {
                $best = $plan;
            }
        }

        return $best ?? ['usable' => false, 'covering' => false, 'expressionMatched' => false, 'partialPredicateImplied' => false];
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function indexes(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
            throw new \InvalidArgumentException('SQLite expression partial covering source needs index definitions');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite expression partial covering indexes must be arrays');
            }
        }

        return $indexes;
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function coveredRows(array $source, array $plan, array $queryTerms, array $neededColumns): array
    {
        if (($plan['usable'] ?? false) !== true || ($plan['covering'] ?? false) !== true) {
            return [];
        }

        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite expression partial covering source rows must be a list');
        }

        $out = [];
        foreach ($rows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite expression partial covering rows must be arrays');
            }
            if (!self::rowSatisfiesTerms($row, $queryTerms, (string) ($plan['expression'] ?? ''))) {
                continue;
            }
            $expressionValue = self::expressionValue((string) ($plan['expression'] ?? ''), $row);
            $out[] = [
                'sourceOffset' => $offset,
                'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                'expressionKey' => $expressionValue,
                'covering' => self::payload($row, $neededColumns),
            ];
        }

        usort($out, static function (array $left, array $right): int {
            $key = self::compare($left['expressionKey'] ?? null, $right['expressionKey'] ?? null);
            if ($key !== 0) {
                return $key;
            }

            return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
        });

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
        }

        return $pairs;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $rows
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $plan, array $rows, array $neededColumns, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Rewind', 'source' => 'table'], ['opcode' => 'DeferredSeek', 'source' => 'table']];
        }

        $program = [
            ['opcode' => 'OpenRead', 'source' => 'index', 'index' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => 'SeekExpression', 'expression' => $plan['expression'] ?? null, 'key' => $plan['expressionValue'] ?? null],
        ];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => 'covering-index', 'column' => $column];
        }
        $program[] = ['opcode' => 'ResultRow', 'rowCount' => count($rows)];
        $program[] = ['opcode' => 'Next', 'source' => 'index'];

        return $program;
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return array<string,mixed>|null
     */
    private static function matchingExpressionTerm(array $queryTerms, string $expression): ?array
    {
        $normalized = self::normalizedExpression($expression);
        foreach ($queryTerms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || self::normalizedExpression((string) ($left['expression'] ?? '')) !== $normalized) {
                continue;
            }
            if (($term['operator'] ?? null) === '=') {
                return $term;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function partialPredicateImplied(array $partialTerms, array $queryTerms): bool
    {
        foreach ($partialTerms as $partial) {
            if (!is_array($partial)) {
                return false;
            }
            $matched = false;
            foreach ($queryTerms as $query) {
                if (self::termImplies($query, $partial)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $partial
     */
    private static function termImplies(array $query, array $partial): bool
    {
        $queryLeft = $query['left'] ?? null;
        $partialLeft = $partial['left'] ?? null;
        if (!is_array($queryLeft) || !is_array($partialLeft)) {
            return false;
        }
        if (($queryLeft['column'] ?? null) !== ($partialLeft['column'] ?? null)) {
            return false;
        }
        $queryOperator = strtoupper((string) ($query['operator'] ?? ''));
        $partialOperator = strtoupper((string) ($partial['operator'] ?? ''));
        if ($partialOperator === '=') {
            return $queryOperator === '=' && ($query['right'] ?? null) === ($partial['right'] ?? null);
        }
        if ($partialOperator === 'IS NOT NULL') {
            return $queryOperator !== 'IS NULL';
        }
        if ($partialOperator === '>=') {
            return in_array($queryOperator, ['=', '>=', '>'], true)
                && self::compare($query['right'] ?? null, $partial['right'] ?? null) >= 0;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function rowSatisfiesTerms(array $row, array $queryTerms, string $expression): bool
    {
        foreach ($queryTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                continue;
            }
            $actual = array_key_exists('expression', $left)
                ? self::expressionValue($expression, $row)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            $expected = $term['right'] ?? null;
            $ok = match ($operator) {
                '=' => $actual === $expected,
                '>=' => self::compare($actual, $expected) >= 0,
                '>' => self::compare($actual, $expected) > 0,
                'IS NOT NULL' => $actual !== null,
                default => true,
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expressionValue(string $expression, array $row): mixed
    {
        $normalized = self::normalizedExpression($expression);
        if (preg_match('/^lower\(([a-z0-9_]+)\)$/', $normalized, $match) === 1) {
            return strtolower((string) ($row[$match[1]] ?? ''));
        }
        if (preg_match('/^upper\(([a-z0-9_]+)\)$/', $normalized, $match) === 1) {
            return strtoupper((string) ($row[$match[1]] ?? ''));
        }
        if (preg_match('/^length\(([a-z0-9_]+)\)$/', $normalized, $match) === 1) {
            return strlen((string) ($row[$match[1]] ?? ''));
        }

        return $row[$normalized] ?? null;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function payload(array $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'indexSignature' => $signature,
            'selectedIndex' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'usable' => (bool) ($plan['usable'] ?? false),
            'covering' => (bool) ($plan['covering'] ?? false),
        ];
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return hash('sha256', json_encode([
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'indexes' => self::indexes($source),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function validateQueryTerms(array $terms): void
    {
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite expression partial covering plan needs query terms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite expression partial covering query terms must be arrays');
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateNeededColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite expression partial covering plan needs covering columns');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite expression partial covering columns must be names');
            }
        }
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite expression partial covering source ' . $key . ' must be a non-negative integer');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $array
     */
    private static function arrayInt(array $array, string $key, int $default = 0): int
    {
        $value = $array[$key] ?? $default;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite expression partial covering index ' . $key . ' must be a non-negative integer');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return list<array<string,mixed>>
     */
    private static function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    private static function normalizedExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', trim($expression)));
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }
}
