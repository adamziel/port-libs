<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAttachWalTempViewCollationPlan
{
    /**
     * @param array<string,array{schema_cookie:int, wal_schema_cookie?:int|null, wal_frames?:list<array{page:int, schema_cookie?:int|null, commit?:bool}>, tables?:list<string>, indexes?:list<string>, file?:string|null, cache?:string|null}> $schemas
     * @param list<string> $triggerNames
     * @return array<string,mixed>
     */
    public static function plan(SQLiteAttachedSchemaCatalog $catalog, array $schemas, array $triggerNames, string $sourceSchema = 'main'): array
    {
        $cache = SQLiteAttachTempMainWalSchemaCachePlan::currentNext($schemas, [], $sourceSchema);
        /** @var list<string> $changedSchemas */
        $changedSchemas = $cache['changed_schemas'];
        $triggerPlans = [];
        $reprepare = [];
        $stable = [];
        $collationCounts = [];

        foreach ($triggerNames as $triggerName) {
            $collationPlan = SQLiteAttachTempViewCollationPlan::forTrigger($catalog, $triggerName);
            $schemaDependencies = self::schemaDependencies($collationPlan);
            $changedDependencies = array_values(array_intersect($schemaDependencies, $changedSchemas));
            $requiresReprepare = $changedDependencies !== [];
            $key = $collationPlan['triggerSchema'] . '.' . $collationPlan['trigger'];

            $triggerPlans[$triggerName] = [
                'trigger' => $collationPlan['trigger'],
                'trigger_schema' => $collationPlan['triggerSchema'],
                'target' => $collationPlan['target'],
                'target_schema' => $collationPlan['targetSchema'],
                'target_type' => $collationPlan['targetType'],
                'schema_dependencies' => $schemaDependencies,
                'changed_schema_dependencies' => $changedDependencies,
                'requires_reprepare' => $requiresReprepare,
                'target_collations' => $collationPlan['targetCollations'],
                'select_collations' => $collationPlan['selectCollations'],
                'body_schema_count' => self::bodySchemaCount($collationPlan),
                'body_collation_count' => self::collationCountFromBody($collationPlan),
                'status' => $requiresReprepare ? 'expired' : 'stable',
            ];

            if ($requiresReprepare) {
                $reprepare[] = $key;
            } else {
                $stable[] = $key;
            }

            foreach ($collationPlan['targetCollations'] as $collation) {
                $normalized = strtoupper($collation);
                $collationCounts[$normalized] = ($collationCounts[$normalized] ?? 0) + 1;
            }
            foreach ($collationPlan['selectCollations'] as $select) {
                $normalized = strtoupper($select['collation']);
                $collationCounts[$normalized] = ($collationCounts[$normalized] ?? 0) + 1;
            }
        }

        sort($reprepare);
        sort($stable);
        ksort($collationCounts);

        return [
            'status' => 'ok',
            'operation' => 'attach-wal-temp-view-collation-current-next',
            'source' => $cache['source'],
            'search_order' => $cache['search_order'],
            'schema_cookies_current' => $cache['schema_cookies_current'],
            'schema_cookies_next' => $cache['schema_cookies_next'],
            'changed_schemas' => $changedSchemas,
            'wal_schema_cookie_sources' => $cache['wal_schema_cookie_sources'],
            'database_list' => $cache['database_list'],
            'trigger_plans' => $triggerPlans,
            'reprepare_triggers' => $reprepare,
            'stable_triggers' => $stable,
            'collation_counts' => $collationCounts,
            'dependencies' => [
                'attach-wal-temp-view-collation-current-next',
                'sqlite-wal-page-one-schema-cookie',
                'sqlite-temp-view-trigger-collation-resolution',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $collationPlan
     * @return list<string>
     */
    private static function schemaDependencies(array $collationPlan): array
    {
        $schemas = [
            (string) $collationPlan['triggerSchema'],
            (string) $collationPlan['targetSchema'],
        ];

        foreach ($collationPlan['body'] as $operation) {
            if (isset($operation['schema'])) {
                $schemas[] = (string) $operation['schema'];
            }
        }

        $schemas = array_values(array_unique($schemas));
        sort($schemas);

        return $schemas;
    }

    /**
     * @param array<string,mixed> $collationPlan
     * @return array<string,int>
     */
    private static function bodySchemaCount(array $collationPlan): array
    {
        $counts = [];
        foreach ($collationPlan['body'] as $operation) {
            if (!isset($operation['schema']) || ($operation['kind'] ?? '') === 'select') {
                continue;
            }
            $schema = (string) $operation['schema'];
            $counts[$schema] = ($counts[$schema] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string,mixed> $collationPlan
     * @return array<string,int>
     */
    private static function collationCountFromBody(array $collationPlan): array
    {
        $counts = [];
        foreach ($collationPlan['bodyCollationsBySchema'] as $tables) {
            foreach ($tables as $collations) {
                foreach ($collations as $collation) {
                    $normalized = strtoupper((string) $collation);
                    $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        return $counts;
    }
}
