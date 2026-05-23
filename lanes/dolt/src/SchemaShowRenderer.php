<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class SchemaShowRenderer
{
    /**
     * Render the stdout shape of `dolt schema show`: one `table @ commit`
     * header followed by the table's CREATE TABLE statement and a blank line.
     *
     * @param array<string, TableSchema> $tables
     * @param list<string> $requestedTables
     */
    public function render(array $tables, array $requestedTables = [], string $commit = 'working'): string
    {
        $commit = $this->commitName($commit);
        $tables = $this->normalizeTables($tables);
        $requestedTables = $this->normalizeRequestedTables($requestedTables);

        $selected = $requestedTables === []
            ? $this->visibleTableNames(array_keys($tables))
            : $requestedTables;

        if ($selected === []) {
            return 'No tables in working set';
        }

        $blocks = [];
        foreach ($selected as $tableName) {
            if ($this->isFullTextTable($tableName)) {
                continue;
            }
            $schema = $this->lookupSchema($tables, $tableName);
            if ($schema === null) {
                continue;
            }

            $blocks[] = $tableName . ' @ ' . $commit . "\n" . $this->createTableStatement($tableName, $schema);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param array<string, TableSchema> $tables
     * @return list<string>
     */
    public function missingTables(array $tables, array $requestedTables): array
    {
        $tables = $this->normalizeTables($tables);
        $missing = [];
        foreach ($this->normalizeRequestedTables($requestedTables) as $tableName) {
            if ($this->isFullTextTable($tableName)) {
                continue;
            }
            if ($this->lookupSchema($tables, $tableName) === null) {
                $missing[] = $tableName;
            }
        }

        return $missing;
    }

    private function createTableStatement(string $tableName, TableSchema $schema): string
    {
        $rows = (new PatchRenderer())->rows([[
            'tableName' => $tableName,
            'fromSchema' => null,
            'toSchema' => $schema,
        ]], ['filter' => PatchRenderer::DIFF_SCHEMA]);

        return $rows[0]['statement'] ?? '';
    }

    /**
     * @param array<string, TableSchema> $tables
     * @return array<string, TableSchema>
     */
    private function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $tableName => $schema) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException('Schema show table names must be non-empty strings.');
            }
            if (!$schema instanceof TableSchema) {
                throw new \InvalidArgumentException("Schema show table {$tableName} must be a TableSchema.");
            }
            $normalized[$tableName] = $schema;
        }

        return $normalized;
    }

    /**
     * @param list<string> $requestedTables
     * @return list<string>
     */
    private function normalizeRequestedTables(array $requestedTables): array
    {
        $normalized = [];
        foreach ($requestedTables as $tableName) {
            if (!is_string($tableName) || $tableName === '') {
                throw new \InvalidArgumentException('Schema show requested table names must be non-empty strings.');
            }
            $normalized[] = $tableName;
        }

        return $normalized;
    }

    /**
     * @param list<string> $tableNames
     * @return list<string>
     */
    private function visibleTableNames(array $tableNames): array
    {
        $visible = array_values(array_filter(
            $tableNames,
            fn (string $tableName): bool => $tableName !== 'dolt_docs' && !$this->isFullTextTable($tableName)
        ));
        sort($visible, SORT_STRING);

        return $visible;
    }

    /**
     * @param array<string, TableSchema> $tables
     */
    private function lookupSchema(array $tables, string $tableName): ?TableSchema
    {
        foreach ($tables as $candidate => $schema) {
            if (strcasecmp($candidate, $tableName) === 0) {
                return $schema;
            }
        }

        return null;
    }

    private function commitName(string $commit): string
    {
        if ($commit === '') {
            throw new \InvalidArgumentException('Schema show commit must be a non-empty string.');
        }

        return $commit;
    }

    private function isFullTextTable(string $tableName): bool
    {
        return str_starts_with($tableName, 'dolt_fulltext_');
    }
}
