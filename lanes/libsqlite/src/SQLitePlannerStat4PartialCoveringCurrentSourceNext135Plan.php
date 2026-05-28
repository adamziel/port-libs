<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialCoveringCurrentSourceNext135Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns
    ): array {
        self::validateNeededColumns($neededColumns);

        $comparison = SQLiteStat4PartialCoveringCurrentSourcePlan::compare(
            $preparedSource,
            $currentSource,
            $predicate,
            $orderBy,
            $neededColumns,
        );
        $selectedSource = (string) $comparison['selectedSource'];
        $source = $selectedSource === 'current' ? $currentSource : $preparedSource;
        $selectedPlan = is_array($comparison['selectedPlan'] ?? null) ? $comparison['selectedPlan'] : [];
        $rows = self::coveredRows($source, $selectedPlan, $predicate, $neededColumns);
        $ready = ($comparison['status'] ?? null) === 'usable'
            && ($selectedPlan['partialPredicateImplied'] ?? false) === true
            && ($selectedPlan['covering'] ?? false) === true
            && ($selectedPlan['stat4Used'] ?? false) === true
            && $rows !== [];

        return array_replace($comparison, [
            'status' => $ready ? 'stat4-partial-covering-current-source-next135-ready' : 'requires-next-stage',
            'selectedPlan' => $selectedPlan + [
                'coveredRowCount' => count($rows),
                'stat4AnchorKeys' => self::stat4AnchorKeys($selectedPlan),
                'rangeLower' => self::rangeBounds($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['lower'],
                'rangeUpper' => self::rangeBounds($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['upper'],
                'lowerInclusive' => self::rangeBounds($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['lowerInclusive'],
                'upperInclusive' => self::rangeBounds($predicate, (string) ($selectedPlan['rangeColumn'] ?? ''))['upperInclusive'],
                'cursorProgram' => self::cursorProgram($selectedPlan, $predicate, $neededColumns),
            ],
            'coveredRows' => $rows,
            'tableLookupElided' => $ready,
            'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
            'currentNextRows' => self::currentNextRows($rows),
            'currentSourceFence' => [
                'schemaCookie' => self::nonNegativeInt($currentSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($currentSource, 'stat4Generation'),
                'indexSignature' => (string) ($comparison['currentSource']['indexSignature'] ?? ''),
                'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                'orderSignature' => self::orderSignature($orderBy),
                'coveringSignature' => implode(',', $neededColumns),
                'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
            ],
            'detail' => ((bool) ($comparison['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 PARTIAL COVERING CURRENT SOURCE ROW STREAM '
                . self::stringValue($source, 'name')
                . ' ' . (string) ($selectedPlan['detail'] ?? 'NO PLAN'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($comparison['dependencies'] ?? null) ? $comparison['dependencies'] : [],
                [
                    'SQLiteStat4PartialCoveringCurrentSourcePlan',
                    'sqlite-sqlplanner-stat4-partial-covering-current-source-next135',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next135 composes native STAT4 partial-covering source fences with lane-local current-row stream materialization',
            'non_overlap' => 'avoids next131 ordinary partial range row streams, next124 partial range deltas, next125/next127 skip-scan covering, next129 partial expression skip-scan, and next132 expression covering skip-scan; this slice covers STAT4 partial-covering current-source row stream admission',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function coveredRows(array $source, array $plan, array $predicate, array $neededColumns): array
    {
        if (
            ($plan['partialPredicateImplied'] ?? false) !== true
            || ($plan['covering'] ?? false) !== true
            || ($plan['stat4Used'] ?? false) !== true
        ) {
            return [];
        }

        $rows = self::listValue($source, 'rows');
        $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
        if ($rangeColumn === '') {
            return [];
        }
        $bounds = self::rangeBounds($predicate, $rangeColumn);
        $anchors = array_fill_keys(array_map([self::class, 'keySignature'], self::stat4AnchorKeys($plan)), true);
        $covered = [];

        foreach ($rows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source rows must be arrays');
            }
            if (!self::rowSatisfiesPointTerms($row, $predicate) || !self::rowSatisfiesEqualities($row, $plan)) {
                continue;
            }
            if (!array_key_exists($rangeColumn, $row) || !self::valueInRange($row[$rangeColumn], $bounds)) {
                continue;
            }

            $payload = [];
            foreach ($neededColumns as $column) {
                $payload[$column] = $row[$column] ?? null;
            }
            $covered[] = [
                'sourceOffset' => $offset,
                'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                'rangeKey' => $row[$rangeColumn],
                'stat4Anchor' => isset($anchors[self::keySignature($row[$rangeColumn])]),
                'covering' => $payload,
            ];
        }

        usort($covered, static function (array $left, array $right): int {
            $comparison = self::compareValues($left['rangeKey'], $right['rangeKey']);
            if ($comparison !== 0) {
                return $comparison;
            }

            return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
        });

        return $covered;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = [
                'current' => $row,
                'next' => $rows[$offset + 1] ?? null,
            ];
        }

        return $pairs;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<mixed>
     */
    private static function stat4AnchorKeys(array $plan): array
    {
        $keys = [];
        foreach (($plan['stat4MatchedCurrentNext'] ?? []) as $pair) {
            if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current']) || !array_key_exists('key', $pair['current'])) {
                continue;
            }
            $keys[] = $pair['current']['key'];
        }

        return $keys;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $plan
     */
    private static function rowSatisfiesEqualities(array $row, array $plan): bool
    {
        $constraints = $plan['equalityConstraints'] ?? [];
        if (!is_array($constraints)) {
            return false;
        }

        foreach ($constraints as $constraint) {
            if (!is_array($constraint)) {
                return false;
            }
            $column = $constraint['column'] ?? null;
            if (!is_string($column) || !array_key_exists($column, $row)) {
                return false;
            }
            $operator = strtoupper((string) ($constraint['operator'] ?? ''));
            $values = $constraint['values'] ?? null;
            if ($operator === 'POINT' && self::compareValues($row[$column], $values) !== 0) {
                return false;
            }
            if ($operator === 'IN') {
                if (!is_array($values)) {
                    return false;
                }
                $matched = false;
                foreach ($values as $value) {
                    if (self::compareValues($row[$column], $value) === 0) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $predicate
     */
    private static function rowSatisfiesPointTerms(array $row, array $predicate): bool
    {
        foreach (self::flattenAndTerms($predicate) as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (!in_array($operator, ['=', '==', 'IS'], true)) {
                continue;
            }
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                continue;
            }
            $column = $left['column'] ?? null;
            if (!is_string($column) || !array_key_exists($column, $row)) {
                return false;
            }
            if (self::compareValues($row[$column], $term['right'] ?? null) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $predicate
     * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}
     */
    private static function rangeBounds(array $predicate, string $column): array
    {
        $lower = null;
        $upper = null;
        $lowerInclusive = false;
        $upperInclusive = false;

        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || strcasecmp((string) ($left['column'] ?? ''), $column) !== 0) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === 'BETWEEN') {
                return [
                    'lower' => $term['lower'] ?? null,
                    'upper' => $term['upper'] ?? null,
                    'lowerInclusive' => true,
                    'upperInclusive' => true,
                ];
            }
            if ($operator === '>' || $operator === '>=') {
                $candidate = $term['right'] ?? null;
                if ($lower === null || self::compareValues($candidate, $lower) > 0) {
                    $lower = $candidate;
                    $lowerInclusive = $operator === '>=';
                }
                continue;
            }
            if ($operator === '<' || $operator === '<=') {
                $candidate = $term['right'] ?? null;
                if ($upper === null || self::compareValues($candidate, $upper) < 0) {
                    $upper = $candidate;
                    $upperInclusive = $operator === '<=';
                }
            }
        }

        return [
            'lower' => $lower,
            'upper' => $upper,
            'lowerInclusive' => $lowerInclusive,
            'upperInclusive' => $upperInclusive,
        ];
    }

    /**
     * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $bounds
     */
    private static function valueInRange(mixed $value, array $bounds): bool
    {
        if ($bounds['lower'] !== null) {
            $comparison = self::compareValues($value, $bounds['lower']);
            if ($comparison < 0 || ($comparison === 0 && !$bounds['lowerInclusive'])) {
                return false;
            }
        }
        if ($bounds['upper'] !== null) {
            $comparison = self::compareValues($value, $bounds['upper']);
            if ($comparison > 0 || ($comparison === 0 && !$bounds['upperInclusive'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $plan, array $predicate, array $neededColumns): array
    {
        if (($plan['usable'] ?? false) !== true) {
            return [['opcode' => 'Rewind', 'source' => 'table']];
        }

        $rangeColumn = (string) ($plan['rangeColumn'] ?? '');
        $bounds = self::rangeBounds($predicate, $rangeColumn);
        $program = [[
            'opcode' => 'OpenRead',
            'target' => 'index',
            'index' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
        ]];
        $program[] = [
            'opcode' => $bounds['lowerInclusive'] ? 'SeekGE' : 'SeekGT',
            'column' => $rangeColumn,
            'value' => $bounds['lower'],
        ];
        $program[] = [
            'opcode' => $bounds['upperInclusive'] ? 'IdxGT' : 'IdxGE',
            'column' => $rangeColumn,
            'value' => $bounds['upper'],
        ];
        foreach ($neededColumns as $column) {
            $program[] = [
                'opcode' => 'Column',
                'source' => 'index',
                'column' => $column,
            ];
        }
        $program[] = ['opcode' => 'Next', 'target' => 'index'];

        return $program;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms)) {
            return [$predicate];
        }

        $flat = [];
        foreach ($terms as $term) {
            if (is_array($term)) {
                array_push($flat, ...self::flattenAndTerms($term));
            }
        }

        return $flat;
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderSignature(array $orderBy): string
    {
        if ($orderBy === []) {
            return 'rowid ASC';
        }

        return implode(', ', array_map(static function (array $term): string {
            $column = $term['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 ORDER BY terms need columns');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 ORDER BY direction must be ASC or DESC');
            }

            return $column . ' ' . $direction;
        }, $orderBy));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs list ' . $key);
        }

        return $value;
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function validateNeededColumns(array $neededColumns): void
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs at least one covering column');
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 covering columns must be names');
            }
        }
    }

    private static function nonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs non-negative integer ' . $key);
        }

        return $value;
    }

    private static function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite STAT4 partial-covering current-source next135 needs string ' . $key);
        }

        return $value;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function keySignature(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }
}
