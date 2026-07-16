<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningSchemaNamePlan
{
    /**
     * @return array{status:string,error:?string,schema:string,returning_rows:list<array{name:mixed}>,dependencies:list<string>}
     */
    public static function writableSchemaDefaultReturningName(string $schema): array
    {
        $schema = self::schemaName($schema);

        return [
            'status' => 'ok',
            'error' => null,
            'schema' => $schema,
            'returning_rows' => [['name' => null]],
            'dependencies' => [
                $schema === 'temp' ? 'returning1.test-21.1' : 'returning1.test-21.0',
            ],
        ];
    }

    /**
     * @param list<string> $userColumns
     * @return array{status:string,error:?string,schema:string,returning_rows:list<array<string,mixed>>,subquery:array{table:string,alias:string,where_left:string,where_right:string},dependencies:list<string>}
     */
    public static function tempSchemaReturningSubqueryNameResolution(array $userColumns, string $alias = 'sqlite_master'): array
    {
        $columns = self::columns($userColumns);
        $alias = self::identifier($alias, 'subquery alias');
        $whereRight = $alias . '.name';

        return [
            'status' => 'error-before-returning',
            'error' => self::missingColumnMessage($columns, $whereRight),
            'schema' => 'temp',
            'returning_rows' => [],
            'subquery' => [
                'table' => 'xyz',
                'alias' => $alias,
                'where_left' => 'a',
                'where_right' => $whereRight,
            ],
            'dependencies' => ['returning1.test-22.1'],
        ];
    }

    /**
     * @return list<array{variant:int,main:array<string,mixed>,temp:array<string,mixed>,subquery:array<string,mixed>}>
     */
    public static function dynamicReturningSchemaCases(int $caseCount = 150): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite RETURNING schema-name corpus case count must be positive');
        }

        $cases = [];
        for ($i = 0; $i < $caseCount; ++$i) {
            $columns = ['a'];
            if ($i % 3 === 1) {
                $columns[] = 'payload_' . $i;
            } elseif ($i % 3 === 2) {
                array_unshift($columns, 'tenant_' . $i);
            }

            $cases[] = [
                'variant' => $i,
                'main' => self::writableSchemaDefaultReturningName('main'),
                'temp' => self::writableSchemaDefaultReturningName('temp'),
                'subquery' => self::tempSchemaReturningSubqueryNameResolution($columns),
            ];
        }

        return $cases;
    }

    private static function schemaName(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema !== 'main' && $schema !== 'temp') {
            throw new \InvalidArgumentException('SQLite RETURNING writable schema name must be main or temp');
        }

        return $schema;
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function columns(array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite RETURNING subquery columns must not be empty');
        }

        $out = [];
        foreach ($columns as $column) {
            $out[] = self::identifier($column, 'subquery column');
        }

        return $out;
    }

    /**
     * @param list<string> $columns
     */
    private static function missingColumnMessage(array $columns, string $qualifiedColumn): string
    {
        $column = substr($qualifiedColumn, (int) strrpos($qualifiedColumn, '.') + 1);
        if (in_array($column, $columns, true)) {
            return 'ambiguous column name: ' . $qualifiedColumn;
        }

        return 'no such column: ' . $qualifiedColumn;
    }

    private static function identifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite RETURNING invalid {$label}: {$value}");
        }

        return $value;
    }
}
