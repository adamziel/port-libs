<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonSchemaWalPlan
{
    /**
     * @param list<array{setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $currentRows
     * @param list<array{setting_id?:int,key_name:string,key_value:string,load_policy?:string}> $importRows
     * @param list<int> $pageNumbers
     * @param list<string> $jsonKeyNames
     * @return array<string,mixed>
     */
    public static function currentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        string $schemaSql,
        array $currentRows,
        array $importRows,
        array $pageNumbers,
        array $jsonKeyNames,
        array $schemaOptions = [],
    ): array {
        if ($jsonKeyNames === []) {
            throw new \InvalidArgumentException('Application JSON schema WAL planning requires at least one JSON setting name');
        }

        $schema = SQLiteSchemaBulkImportPlan::plan($schemaSql, [], $schemaOptions);
        $schemaNames = array_map('strtolower', $schema['ordered_names']);
        if (!in_array('app_settings', $schemaNames, true)) {
            throw new \InvalidArgumentException('Application JSON schema WAL planning requires a app_settings schema object');
        }

        $jsonNames = [];
        foreach ($jsonKeyNames as $name) {
            $normalized = trim($name);
            if ($normalized === '') {
                throw new \InvalidArgumentException('Application JSON schema WAL planning requires non-empty JSON setting names');
            }
            $jsonNames[$normalized] = true;
        }

        $acceptedRows = [];
        $rejectedRows = [];
        foreach ($importRows as $row) {
            $name = trim((string) ($row['key_name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Application JSON schema WAL planning requires key_name');
            }

            if (!isset($jsonNames[$name])) {
                $acceptedRows[] = $row;
                continue;
            }

            $value = (string) ($row['key_value'] ?? '');
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $rejectedRows[] = [
                    'key_name' => $name,
                    'reason' => 'malformed_json_key_value',
                    'error' => json_last_error_msg(),
                ];
                continue;
            }

            $acceptedRows[] = $row;
        }

        if ($acceptedRows === []) {
            throw new \InvalidArgumentException('Application JSON schema WAL planning requires at least one accepted import row');
        }

        $walPlan = SQLiteKeyValueRowsWalImportPlan::currentNext(
            $wal,
            $databaseBytes,
            $databasePath,
            $currentRows,
            $acceptedRows,
            $pageNumbers,
        );

        return [
            'status' => 'planned',
            'reason' => 'application_json_schema_wal_current_next',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'schema' => $schema,
            'schema_object_names' => $schema['ordered_names'],
            'json_key_names' => array_keys($jsonNames),
            'accepted_import_count' => count($acceptedRows),
            'rejected_import_count' => count($rejectedRows),
            'rejected_rows' => $rejectedRows,
            'wal_import' => $walPlan,
            'current_reader_sources' => $walPlan['current_reader_sources'],
            'next_reader_sources' => $walPlan['next_reader_sources'],
            'next_reader_frame_indexes' => $walPlan['next_reader_frame_indexes'],
            'inserted_key_names' => $walPlan['inserted_key_names'],
            'updated_key_names' => $walPlan['updated_key_names'],
            'load_policy_yes_key_names' => $walPlan['load_policy_yes_key_names'],
            'schema_version_after' => $schema['schema_version_after'],
            'data_version_after' => $schema['data_version_after'],
            'wal_last_commit_frame' => $walPlan['append']['last_commit_frame'],
            'wal_database_page_count' => $walPlan['database_page_count'],
            'dependencies' => array_values(array_unique(array_merge(
                $schema['dependencies'],
                $walPlan['dependencies'],
                ['application-json-schema-wal-current-next']
            ))),
        ];
    }
}
