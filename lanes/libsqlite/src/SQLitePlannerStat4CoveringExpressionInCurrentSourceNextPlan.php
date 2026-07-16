<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4CoveringExpressionInCurrentSourceNextPlan
{

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materialize(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $currentRows,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            self::validateNeededColumns($neededColumns);
            self::validateInPredicate($predicate);

            $preparedPlan = self::sourcePlan($preparedSource, $predicate, $neededColumns, $neededExpressions);
            $currentPlan = self::sourcePlan($currentSource, $predicate, $neededColumns, $neededExpressions);
            $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
            $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
            $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
            $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
            $preparedSignature = self::signature($preparedSource);
            $currentSignature = self::signature($currentSource);
            $stale = $preparedCookie !== $currentCookie
                || $preparedStat4 !== $currentStat4
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $currentPlan : $preparedPlan;
            $sourceName = $stale ? 'current' : 'prepared';
            $rows = self::coveringRows($selected, $predicate, $currentRows, $neededColumns, $neededExpressions);
            $ready = ($selected['usable'] ?? false) === true
                && ($selected['covering'] ?? false) === true
                && ($selected['stat4Used'] ?? false) === true
                && ($selected['operator'] ?? null) === 'IN'
                && $rows !== [];

            return [
                'status' => $ready ? 'stat4-covering-expression-in-current-source-ready' : 'requires-next-stage',
                'selectedSource' => $sourceName,
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => $preparedCookie !== $currentCookie,
                'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::summary($preparedSource, $preparedPlan, $preparedSignature),
                'currentSource' => self::summary($currentSource, $currentPlan, $currentSignature),
                'selectedPlan' => $selected + ['coveredRowCount' => count($rows)],
                'coveringPayloadColumns' => array_values($neededColumns),
                'coveringExpressionCount' => count($neededExpressions),
                'tableLookupElided' => $ready,
                'deferredTableSeekOpcode' => $ready ? null : 'DeferredSeek',
                'tempSorterElided' => $ready,
                'currentNextRows' => self::currentNext($rows),
                'cursorTape' => self::cursorTape($selected, $rows, $neededColumns, $ready, $sourceName),
                'currentSourceFence' => [
                    'schemaCookie' => $currentCookie,
                    'stat4Generation' => $currentStat4,
                    'indexSignature' => $currentSignature,
                    'predicateSignature' => hash('sha256', json_encode($predicate, JSON_THROW_ON_ERROR)),
                    'coveringSignature' => implode(',', array_map('strtolower', $neededColumns)),
                ],
                'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                    . ' COVERING EXPRESSION STAT4 IN '
                    . (string) ($selected['name'] ?? self::stringValue($stale ? $currentSource : $preparedSource, 'name')),
                'dependencies' => [
                    'SQLiteSelectExpressionIndexPlan IN STAT4 planner',
                    'sqlite-planner-stat4-covering-expression-in-current-source',
                ],
                'dependency_closure' => 'no new support component needed; reuses native expression-index parsing, STAT4 samples, and covering cursor diagnostics',
                'non_overlap' => 'avoids accepted next122 bounded range covering expression STAT4 and next109 JSON expression covering streams by materializing only multi-seek IN expression probes against current-source STAT4 samples',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $predicate
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        private static function sourcePlan(array $source, array $predicate, array $neededColumns, array $neededExpressions): array
        {
            $plan = SQLiteSelectExpressionIndexPlan::chooseLowestCost(
                self::indexes($source),
                $predicate,
                [],
                $neededColumns,
                $neededExpressions,
            );

            return $plan ?? [
                'usable' => false,
                'covering' => false,
                'stat4Used' => false,
                'operator' => null,
                'estimatedRows' => 0,
                'stat4MatchedSamples' => 0,
                'detail' => 'SCAN TABLE; NO COVERING EXPRESSION STAT4 IN',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @return list<array<string,mixed>>
         */
        private static function indexes(array $source): array
        {
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
                throw new \InvalidArgumentException('SQLite STAT4 covering expression IN source needs index definitions');
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite STAT4 covering expression IN indexes must be arrays');
                }
            }

            return $indexes;
        }

        /**
         * @param list<string> $neededColumns
         */
        private static function validateNeededColumns(array $neededColumns): void
        {
            if ($neededColumns === []) {
                throw new \InvalidArgumentException('SQLite STAT4 covering expression IN plan needs at least one output column');
            }
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite STAT4 covering expression IN output columns must be names');
                }
            }
        }

        /**
         * @param array<string,mixed> $predicate
         */
        private static function validateInPredicate(array $predicate): void
        {
            if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'IN') {
                throw new \InvalidArgumentException('SQLite STAT4 covering expression IN plan needs an IN predicate');
            }
            $values = $predicate['values'] ?? null;
            if (!is_array($values) || !array_is_list($values) || $values === []) {
                throw new \InvalidArgumentException('SQLite STAT4 covering expression IN plan needs a non-empty value list');
            }
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function nonNegativeInt(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite STAT4 covering expression IN source ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function signature(array $source): string
        {
            $parts = [];
            foreach (self::indexes($source) as $index) {
                $parts[] = implode('|', [
                    (string) ($index['name'] ?? ''),
                    (string) ($index['rootPage'] ?? ''),
                    (string) ($index['sql'] ?? ''),
                    implode(',', array_map('strtolower', is_array($index['coveringColumns'] ?? null) ? $index['coveringColumns'] : [])),
                    hash('sha256', serialize($index['stat4Samples'] ?? [])),
                ]);
            }

            return hash('sha256', implode("\n", $parts));
        }

        /**
         * @param array<string,mixed> $source
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function summary(array $source, array $plan, string $signature): array
        {
            return [
                'name' => self::stringValue($source, 'name'),
                'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => (bool) ($plan['usable'] ?? false),
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'estimatedRows' => (int) ($plan['estimatedRows'] ?? 0),
                'stat4MatchedSamples' => (int) ($plan['stat4MatchedSamples'] ?? 0),
                'covering' => (bool) ($plan['covering'] ?? false),
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return list<array<string,mixed>>
         */
        private static function coveringRows(array $plan, array $predicate, array $currentRows, array $neededColumns, array $neededExpressions): array
        {
            if (($plan['usable'] ?? false) !== true || ($plan['covering'] ?? false) !== true || ($plan['operator'] ?? null) !== 'IN') {
                return [];
            }

            $wanted = [];
            foreach (($predicate['values'] ?? []) as $value) {
                $wanted[self::keySignature($value)] = $value;
            }
            $stat4Keys = [];
            foreach (($plan['stat4MatchedCurrentNext'] ?? []) as $pair) {
                if (is_array($pair) && isset($pair['current']) && is_array($pair['current']) && array_key_exists('key', $pair['current'])) {
                    $stat4Keys[self::keySignature($pair['current']['key'])] = $pair['current']['key'];
                }
            }
            if ($wanted === [] || $stat4Keys === []) {
                return [];
            }

            $rows = [];
            foreach ($currentRows as $offset => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite STAT4 covering expression IN current rows must be arrays');
                }
                if (!self::rowSatisfiesEqualities($row, $predicate)) {
                    continue;
                }
                $key = self::expressionValue($plan, $row);
                $signature = self::keySignature($key);
                if (!array_key_exists($signature, $wanted) || !array_key_exists($signature, $stat4Keys)) {
                    continue;
                }

                $rows[] = [
                    'sourceOffset' => $offset,
                    'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                    'key' => $key,
                    'covering' => self::payload($row, $neededColumns),
                    'coveringExpressions' => self::expressionPayload($plan, $row, $neededExpressions),
                ];
            }

            usort($rows, static function (array $left, array $right): int {
                $comparison = self::compareKeys($left['key'], $right['key']);
                if ($comparison !== 0) {
                    return $comparison;
                }

                return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
            });

            return $rows;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
         */
        private static function currentNext(array $rows): array
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
         * @return array<string,mixed>
         */
        private static function cursorTape(array $plan, array $rows, array $neededColumns, bool $ready, string $source): array
        {
            $values = is_array($plan['values'] ?? null) ? array_values($plan['values']) : [];
            $program = [];
            foreach ($values as $value) {
                $program[] = ['opcode' => 'SeekGE', 'source' => 'index', 'key' => $value];
                $program[] = ['opcode' => 'IdxGT', 'source' => 'index', 'key' => $value];
                foreach ($neededColumns as $column) {
                    $program[] = ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column];
                }
                $program[] = ['opcode' => 'ResultRow', 'source' => $ready ? 'covering-index' : 'table'];
            }

            return [
                'source' => $source,
                'indexName' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'expressionType' => $plan['type'] ?? null,
                'expressionColumn' => $plan['column'] ?? null,
                'seekKeys' => $values,
                'seekOpcode' => 'SeekGE',
                'stopOpcode' => 'IdxGT',
                'matchedKeys' => array_map(static fn (array $row): mixed => $row['key'], $rows),
                'currentNextRows' => self::currentNext($rows),
                'outputColumns' => array_map(static fn (string $column): array => ['opcode' => 'Column', 'source' => $ready ? 'index' : 'table', 'column' => $column], $neededColumns),
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
                'sorterOpen' => false,
                'dedupeByRowid' => count(array_unique(array_map(static fn (array $row): mixed => $row['rowid'] ?? $row['sourceOffset'], $rows), SORT_REGULAR)) !== count($rows),
                'program' => $program,
            ];
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,mixed> $predicate
         */
        private static function rowSatisfiesEqualities(array $row, array $predicate): bool
        {
            foreach (($predicate['terms'] ?? []) as $term) {
                if (!is_array($term) || strtoupper((string) ($term['operator'] ?? '')) !== '=') {
                    continue;
                }
                $left = $term['left'] ?? null;
                if (!is_array($left) || isset($left['function'])) {
                    continue;
                }
                $column = $left['column'] ?? null;
                if (is_string($column) && ($row[$column] ?? null) !== ($term['right'] ?? null)) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $row
         */
        private static function expressionValue(array $plan, array $row): mixed
        {
            $column = (string) ($plan['column'] ?? '');
            $value = $row[$column] ?? null;

            return match ((string) ($plan['type'] ?? '')) {
                'lower' => strtolower((string) $value),
                'upper' => strtoupper((string) $value),
                'length' => strlen((string) $value),
                'integer-cast' => (int) $value,
                'json-extract', 'jsonb-extract', 'json-text-operator', 'json-value-operator' => self::jsonValue($value, $plan['path'] ?? null),
                default => $value,
            };
        }

        private static function jsonValue(mixed $json, mixed $path): mixed
        {
            if (!is_string($json) || !is_string($path) || !str_starts_with($path, '$.')) {
                return null;
            }

            try {
                $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }

            foreach (explode('.', substr($path, 2)) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }

            return is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
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
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $row
         * @param list<array<string,string>> $expressions
         * @return array<string,mixed>
         */
        private static function expressionPayload(array $plan, array $row, array $expressions): array
        {
            $payload = [];
            foreach ($expressions as $expression) {
                $function = strtolower((string) ($expression['function'] ?? ''));
                $column = (string) ($expression['column'] ?? '');
                if ($function === '' || $column === '') {
                    continue;
                }
                $payload[$function . '(' . $column . ')'] = self::expressionValue([
                    'type' => $function === 'cast_integer' ? 'integer-cast' : $function,
                    'column' => $column,
                    'path' => $expression['path'] ?? null,
                ], $row);
            }
            if ($payload === [] && isset($plan['type'], $plan['column'])) {
                $payload[(string) $plan['type'] . '(' . (string) $plan['column'] . ')'] = self::expressionValue($plan, $row);
            }

            return $payload;
        }

        private static function compareKeys(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return ((float) $left) <=> ((float) $right);
            }

            return strcmp((string) $left, (string) $right);
        }

        private static function keySignature(mixed $key): string
        {
            return get_debug_type($key) . ':' . serialize($key);
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function stringValue(array $source, string $key): string
        {
            $value = $source[$key] ?? '';

            return is_string($value) ? $value : '';
        }

}
