<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSchemaPragmaDdlCurrent
{
    /**
     * @param list<SQLiteSchemaRecord> $records
     * @param list<string> $ddl
     * @param list<array{id:string,schema_cookie:int,sql:string,target?:string}> $preparedStatements
     * @param array<string,list<array<string,mixed>>> $currentRowsByTable
     * @param array<string,array<string,int|bool>> $pragmaState
     * @return array<string,mixed>
     */
    public static function apply(
        array $records,
        array $ddl,
        array $pragmaState = [],
        string $schema = 'main',
        array $preparedStatements = [],
        array $currentRowsByTable = [],
    ): array {
        $versions = new SQLitePragmaSchemaDataVersion($pragmaState === [] ? [$schema => []] : $pragmaState);
        $beforeSchema = $versions->execute('PRAGMA ' . $schema . '.schema_version');
        $beforeData = $versions->execute('PRAGMA ' . $schema . '.data_version');
        $schemaCookie = (int) $beforeSchema['value'];

        $ddlPlan = SQLiteSchemaDdlReparsePlan::apply(
            $records,
            $ddl,
            $schemaCookie,
            $schema,
            $preparedStatements,
            $currentRowsByTable,
        );

        $schemaDelta = max(0, (int) $ddlPlan['after_schema_cookie'] - $schemaCookie);
        $schemaVersionUpdate = $schemaDelta > 0
            ? $versions->recordSchemaChange($schema, $schemaDelta, 'local_schema_ddl')
            : $versions->execute('PRAGMA ' . $schema . '.schema_version');
        $afterData = $versions->execute('PRAGMA ' . $schema . '.data_version');
        $catalog = new SQLitePragmaSchemaCatalog($ddlPlan['records']);

        return [
            'status' => 'ok',
            'schema' => $ddlPlan['schema'],
            'ddl_plan' => $ddlPlan,
            'schema_changed' => $ddlPlan['schema_changed'],
            'schema_delta' => $schemaDelta,
            'pragma_before' => [
                'schema_version' => $beforeSchema,
                'data_version' => $beforeData,
            ],
            'pragma_after' => [
                'schema_version' => $schemaVersionUpdate,
                'data_version' => $afterData,
            ],
            'header_before' => $beforeSchema['header'],
            'header_after' => $versions->headerUpdate($schema),
            'local_data_version_changed' => $beforeData['value'] !== $afterData['value'],
            'invalidated_prepared' => $ddlPlan['invalidated_prepared'],
            'pragma_samples' => self::pragmaSamples($catalog, $ddlPlan['operations']),
            'version_state' => $versions->state(),
            'dependencies' => [
                'schema-sql-reparse',
                'sqlite-schema-cookie',
                'pragma-schema-catalog',
                'pragma-schema-data-version',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return array<string,array<string,mixed>>
     */
    private static function pragmaSamples(SQLitePragmaSchemaCatalog $catalog, array $operations): array
    {
        $samples = [];
        foreach ($operations as $operation) {
            $kind = (string) ($operation['kind'] ?? '');
            $table = (string) ($operation['table'] ?? $operation['name'] ?? '');

            if (in_array($kind, ['create_table', 'alter_table_add_column'], true) && $table !== '') {
                $samples['table_xinfo:' . $table] = $catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $table) . '")');
            }

            if (in_array($kind, ['create_index', 'drop_index'], true) && $table !== '') {
                $samples['index_list:' . $table] = $catalog->execute('PRAGMA index_list("' . str_replace('"', '""', $table) . '")');
            }

            if ($kind === 'alter_table_rename') {
                $new = (string) ($operation['new_name'] ?? '');
                if ($new !== '') {
                    $samples['table_xinfo:' . $new] = $catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $new) . '")');
                    $samples['index_list:' . $new] = $catalog->execute('PRAGMA index_list("' . str_replace('"', '""', $new) . '")');
                }
            }

            if ($kind === 'drop_table') {
                $name = (string) ($operation['name'] ?? '');
                if ($name !== '') {
                    $samples['table_xinfo:' . $name] = $catalog->execute('PRAGMA table_xinfo("' . str_replace('"', '""', $name) . '")');
                    $samples['index_list:' . $name] = $catalog->execute('PRAGMA index_list("' . str_replace('"', '""', $name) . '")');
                }
            }
        }

        return $samples;
    }
}
