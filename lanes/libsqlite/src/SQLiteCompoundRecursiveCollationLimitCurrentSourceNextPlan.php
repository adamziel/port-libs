<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareRecursiveCollationLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive collation limit current-source plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-recursive-collation-limit-current-source-next132-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentNames' => array_values(array_map(static fn (array $row): mixed => $row['name'] ?? null, $currentRows)),
                'nextNames' => array_values(array_map(static fn (array $row): mixed => $row['name'] ?? null, $nextRows)),
                'changedNames' => self::changedNames($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'setCollations' => self::setCollations($currentPlan),
                    'orderBy' => self::orderBy($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'recursive' => self::recursiveSummary($sql, $currentTables, $nextTables),
                'collation' => [
                    'currentFoldedNames' => self::foldedNames($currentRows),
                    'nextFoldedNames' => self::foldedNames($nextRows),
                    'currentDuplicateKeys' => self::duplicateFoldedKeys($currentRows),
                    'nextDuplicateKeys' => self::duplicateFoldedKeys($nextRows),
                ],
                'limitWindow' => [
                    'currentReturned' => count($currentRows),
                    'nextReturned' => count($nextRows),
                    'currentSuppressedByLimit' => max(0, count(self::unlimitedRows($sql, $currentTables)) - count($currentRows)),
                    'nextSuppressedByLimit' => max(0, count(self::unlimitedRows($sql, $nextTables)) - count($nextRows)),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan),
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return array<string,string>
         */
        private static function setCollations(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $firstArm = is_array($compound) && is_array($compound['arms'] ?? null) ? ($compound['arms'][0] ?? null) : null;
            if (!is_array($firstArm) || !is_array($firstArm['select'] ?? null)) {
                return [];
            }

            $collations = [];
            foreach ($firstArm['select'] as $index => $term) {
                if (!is_array($term) || ($term['type'] ?? null) !== 'collate' || !isset($term['collation']) || !is_string($term['collation'])) {
                    continue;
                }
                $column = self::outputColumn($term, $index + 1);
                $collations[$column] = strtoupper($term['collation']);
            }

            return $collations;
        }

        /**
         * @param array<string,mixed> $term
         */
        private static function outputColumn(array $term, int $ordinal): string
        {
            if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
                return $term['alias'];
            }
            if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name'])) {
                return $term['name'];
            }

            return 'expr' . $ordinal;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function orderBy(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return $compound['orderBy'];
        }

        /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        private static function recursiveSummary(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceSql($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'name' => $currentTrace['name'],
                'columns' => $currentTrace['columns'],
                'currentRows' => $currentTrace['rows'],
                'nextRows' => $nextTrace['rows'],
                'currentSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $currentTrace['skipped'])),
                'nextSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $nextTrace['skipped'])),
                'currentTraceCount' => count($currentTrace['trace']),
                'nextTraceCount' => count($nextTrace['trace']),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
            ];
        }

        private static function traceSql(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (stripos($sql, 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound recursive collation limit current-source plan needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+name\s+COLLATE\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive collation limit current-source plan cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT name, depth FROM wanted';
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedNames(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(
                array_diff(self::nameKeys($currentRows), self::nameKeys($nextRows)),
                array_diff(self::nameKeys($nextRows), self::nameKeys($currentRows)),
            ));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function nameKeys(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row['name'] ?? null, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function foldedNames(array $rows): array
        {
            $names = [];
            foreach ($rows as $row) {
                $name = $row['name'] ?? null;
                $names[] = is_string($name) ? strtolower($name) : 'null';
            }

            return $names;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function duplicateFoldedKeys(array $rows): array
        {
            $counts = [];
            foreach (self::foldedNames($rows) as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }

            return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
        }

        /**
         * @param array<string,list<array<string,mixed>>> $tables
         * @return list<array<string,mixed>>
         */
        private static function unlimitedRows(string $sql, array $tables): array
        {
            $withoutLimit = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*;?\s*$/i', '', trim($sql));
            if (!is_string($withoutLimit) || $withoutLimit === trim($sql)) {
                return [];
            }

            return SQLiteSelectSql::execute($withoutLimit, $tables);
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::nameKeys($currentRows) !== self::nameKeys($nextRows)) {
                $reasons[] = 'compound-rowset-changed';
            }
            if (self::setCollations($currentPlan) !== []) {
                $reasons[] = 'compound-set-collation';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-final-limit';
            }
            if (self::setCollations($currentPlan) !== self::setCollations($nextPlan)) {
                $reasons[] = 'compound-collation-plan-changed';
            }

            return $reasons;
        }

}
