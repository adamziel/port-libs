<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteAttachTempMainWalCollationCachePlan
{
    /**
     * @param list<string> $triggerNames
     * @param array<string,array<string,mixed>> $schemaWal
     * @param array<string,array{schema_cookie?:int,wal_schema_cookie?:int,wal_frames?:list<array{page?:int,schema_cookie?:int,commit?:bool}>,registered_collations?:list<string>}> $collationCache
     * @param array<string,array<string,mixed>> $newRows
     * @param array<string,array<string,mixed>|null> $oldRows
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteAttachedSchemaCatalog $catalog,
        array $triggerNames,
        array $schemaWal,
        array $collationCache,
        array $newRows = [],
        array $oldRows = [],
        string $sourceSchema = 'main'
    ): array {
        if ($triggerNames === []) {
            throw new InvalidArgumentException('SQLite attach/temp/main WAL collation cache planning requires triggers');
        }

        $source = self::normalizeSchemaName($sourceSchema);
        if (!in_array($source, $catalog->searchOrder(), true)) {
            throw new InvalidArgumentException("SQLite schema {$source} is not attached");
        }

        $schemaStates = self::schemaStates($catalog, $collationCache);
        $changedSchemas = array_keys(array_filter(
            $schemaStates,
            static fn (array $state): bool => (bool) $state['changed'],
        ));
        sort($changedSchemas);

        $triggerPlans = [];
        $stableTriggers = [];
        $expiredTriggers = [];
        $routeCounts = [];
        $requiredCollations = [];

        foreach ($triggerNames as $triggerName) {
            $trigger = self::normalizeTriggerName($triggerName);
            $newRow = $newRows[$triggerName] ?? $newRows[$trigger] ?? [];
            $oldRow = $oldRows[$triggerName] ?? $oldRows[$trigger] ?? null;
            $yieldPlan = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, $triggerName, $schemaWal, $newRow, $oldRow);
            $collationPlan = SQLiteAttachTempViewCollationPlan::forTrigger($catalog, $triggerName);
            $dependencies = self::triggerSchemaDependencies($yieldPlan, $collationPlan);
            $changedDependencies = array_values(array_intersect($dependencies, $changedSchemas));
            $collations = self::triggerCollations($collationPlan);
            $missingCollations = self::missingCollations($schemaStates, $dependencies, $collations);
            $requiresReprepare = $changedDependencies !== [] || $missingCollations !== [];

            foreach ($collations as $collation) {
                $requiredCollations[$collation] = true;
            }
            foreach ($yieldPlan['operation_routes'] as $route) {
                $journal = (string) ($route['journal'] ?? 'none');
                $routeCounts[$journal] = ($routeCounts[$journal] ?? 0) + 1;
            }

            if ($requiresReprepare) {
                $expiredTriggers[] = $trigger;
            } else {
                $stableTriggers[] = $trigger;
            }

            $triggerPlans[$trigger] = [
                'trigger' => $trigger,
                'trigger_schema' => $yieldPlan['trigger_schema'],
                'target_schema' => $yieldPlan['target_schema'],
                'operation_count' => $yieldPlan['operation_count'],
                'read_count' => $yieldPlan['read_count'],
                'writes_by_schema' => $yieldPlan['writes_by_schema'],
                'wal_schemas' => $yieldPlan['wal_schemas'],
                'temp_schemas' => $yieldPlan['temp_schemas'],
                'rollback_schemas' => $yieldPlan['rollback_schemas'],
                'operation_routes' => $yieldPlan['operation_routes'],
                'schema_dependencies' => $dependencies,
                'changed_schema_dependencies' => $changedDependencies,
                'required_collations' => $collations,
                'missing_collations' => $missingCollations,
                'requires_reprepare' => $requiresReprepare,
                'status' => $requiresReprepare ? 'expired' : 'stable',
            ];
        }
        ksort($triggerPlans);
        sort($stableTriggers);
        sort($expiredTriggers);
        ksort($routeCounts);

        return [
            'status' => 'planned',
            'source' => $source,
            'search_order' => $catalog->searchOrder(),
            'database_list' => $catalog->databaseList(),
            'schema_states' => $schemaStates,
            'schema_cookies_current' => array_map(static fn (array $state): int => (int) $state['current_cookie'], $schemaStates),
            'schema_cookies_next' => array_map(static fn (array $state): int => (int) $state['next_cookie'], $schemaStates),
            'changed_schemas' => $changedSchemas,
            'trigger_count' => count($triggerPlans),
            'expired_triggers' => $expiredTriggers,
            'stable_triggers' => $stableTriggers,
            'trigger_plans' => $triggerPlans,
            'route_counts' => $routeCounts,
            'required_collations' => array_keys($requiredCollations),
            'dependencies' => [
                'sqlite-attach-temp-main-wal-collation-cache-current-next',
                'sqlite-attach-temp-wal-view-trigger-current-next',
                'sqlite-temp-view-trigger-collation-resolution',
                'sqlite-wal-page-one-schema-cookie',
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $collationCache
     * @return array<string,array<string,mixed>>
     */
    private static function schemaStates(SQLiteAttachedSchemaCatalog $catalog, array $collationCache): array
    {
        $states = [];
        foreach ($catalog->searchOrder() as $schema) {
            $entry = $collationCache[$schema] ?? [];
            $current = self::integer($entry['schema_cookie'] ?? 0, "schema cookie for {$schema}");
            $next = self::nextSchemaCookie($entry, $current);
            $registered = array_values(array_unique(array_map(
                static fn (string $collation): string => strtoupper($collation),
                $entry['registered_collations'] ?? ['BINARY', 'NOCASE', 'RTRIM'],
            )));
            sort($registered);

            $states[$schema] = [
                'schema' => $schema,
                'current_cookie' => $current,
                'next_cookie' => $next,
                'changed' => $current !== $next,
                'registered_collations' => $registered,
            ];
        }

        return $states;
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function nextSchemaCookie(array $entry, int $current): int
    {
        if (array_key_exists('wal_schema_cookie', $entry)) {
            return self::integer($entry['wal_schema_cookie'], 'WAL schema cookie');
        }

        foreach (($entry['wal_frames'] ?? []) as $frame) {
            if ((int) ($frame['page'] ?? 0) === 1 && (bool) ($frame['commit'] ?? false)) {
                return self::integer($frame['schema_cookie'] ?? $current, 'WAL frame schema cookie');
            }
        }

        return $current;
    }

    private static function integer(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException("SQLite attach/temp/main WAL collation cache requires integer {$label}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $yieldPlan
     * @param array<string,mixed> $collationPlan
     * @return list<string>
     */
    private static function triggerSchemaDependencies(array $yieldPlan, array $collationPlan): array
    {
        $schemas = [
            (string) $yieldPlan['trigger_schema'],
            (string) $yieldPlan['target_schema'],
        ];
        foreach (array_keys($yieldPlan['writes_by_schema'] ?? []) as $schema) {
            $schemas[] = (string) $schema;
        }
        foreach (($collationPlan['bodyCollationsBySchema'] ?? []) as $schema => $_tables) {
            $schemas[] = (string) $schema;
        }

        $schemas = array_values(array_unique($schemas));
        sort($schemas);

        return $schemas;
    }

    /**
     * @param array<string,mixed> $collationPlan
     * @return list<string>
     */
    private static function triggerCollations(array $collationPlan): array
    {
        $collations = [];
        foreach (($collationPlan['targetCollations'] ?? []) as $collation) {
            $collations[] = strtoupper((string) $collation);
        }
        foreach (($collationPlan['bodyCollationsBySchema'] ?? []) as $tables) {
            foreach ($tables as $columns) {
                foreach ($columns as $collation) {
                    $collations[] = strtoupper((string) $collation);
                }
            }
        }
        foreach (($collationPlan['selectCollations'] ?? []) as $select) {
            $collations[] = strtoupper((string) ($select['collation'] ?? 'BINARY'));
        }

        $collations = array_values(array_unique($collations));
        sort($collations);

        return $collations;
    }

    /**
     * @param array<string,array<string,mixed>> $schemaStates
     * @param list<string> $schemas
     * @param list<string> $collations
     * @return list<string>
     */
    private static function missingCollations(array $schemaStates, array $schemas, array $collations): array
    {
        $missing = [];
        foreach ($schemas as $schema) {
            $registered = $schemaStates[$schema]['registered_collations'] ?? [];
            foreach ($collations as $collation) {
                if (!in_array($collation, $registered, true)) {
                    $missing[] = $schema . ':' . $collation;
                }
            }
        }

        $missing = array_values(array_unique($missing));
        sort($missing);

        return $missing;
    }

    private static function normalizeTriggerName(string $triggerName): string
    {
        $parts = preg_split('/\s*\.\s*/', trim($triggerName), 2);
        if ($parts === false || $parts === []) {
            throw new InvalidArgumentException('SQLite trigger name cannot be empty');
        }

        return implode('.', array_map([self::class, 'normalizeSchemaName'], $parts));
    }

    private static function normalizeSchemaName(string $name): string
    {
        $normalized = strtolower(trim($name, " \t\r\n`\"[]"));
        if ($normalized === '') {
            throw new InvalidArgumentException('SQLite schema name cannot be empty');
        }

        return $normalized;
    }
}
