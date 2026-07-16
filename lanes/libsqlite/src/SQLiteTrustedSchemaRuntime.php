<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;
use RuntimeException;

final class SQLiteTrustedSchemaRuntime
{
    /** @var array<string,array{innocuous:bool,direct_only:bool,deterministic:bool}> */
    private array $functions = [];

    /** @var array<string,array<string,array{columns:list<array{name:string,generated?:string,default?:string}>,checks:list<string>,rows:list<array<string,int|string|null>>}>> */
    private array $tables = ['main' => [], 'temp' => []];

    /** @var array<string,array<string,array{table:string,expressions:list<string>,where:string|null}>> */
    private array $indexes = ['main' => [], 'temp' => []];

    /** @var array<string,array<string,array{source:string,projections:list<array{alias:string,expression:string}>}>> */
    private array $views = ['main' => [], 'temp' => []];

    /** @var array<string,array<string,array{table:string,expression:string,target:string}>> */
    private array $triggers = ['main' => [], 'temp' => []];

    /**
     * @param array<string,array{innocuous?:bool,direct_only?:bool,directOnly?:bool,deterministic?:bool}> $functions
     */
    public function __construct(array $functions = [], private bool $trustedSchema = true)
    {
        $this->registerFunction('json_extract', true, false, true);
        foreach ($functions as $name => $options) {
            $this->registerFunction(
                (string) $name,
                (bool) ($options['innocuous'] ?? false),
                (bool) ($options['direct_only'] ?? $options['directOnly'] ?? false),
                (bool) ($options['deterministic'] ?? true),
            );
        }
    }

    /**
     * @return array{name:string,innocuous:bool,direct_only:bool,deterministic:bool}
     */
    public function registerFunction(string $name, bool $innocuous = false, bool $directOnly = false, bool $deterministic = true): array
    {
        $name = self::identifier($name, 'SQLite function name');
        $this->functions[$name] = [
            'innocuous' => $innocuous,
            'direct_only' => $directOnly,
            'deterministic' => $deterministic,
        ];

        return ['name' => $name] + $this->functions[$name];
    }

    /**
     * @return array{status:string,pragma:'trusted_schema',requested:bool|null,value:int,changed:bool,rows:list<array{trusted_schema:int}>,assignment_returns_rows:false}
     */
    public function executePragma(string $sql): array
    {
        $parsed = self::parseTrustedSchemaPragma($sql);
        $before = $this->trustedSchema;
        if ($parsed['value'] !== null) {
            $this->trustedSchema = $parsed['value'];
        }

        return [
            'status' => 'ok',
            'pragma' => 'trusted_schema',
            'requested' => $parsed['value'],
            'value' => $this->trustedSchema ? 1 : 0,
            'changed' => $before !== $this->trustedSchema,
            'rows' => [['trusted_schema' => $this->trustedSchema ? 1 : 0]],
            'assignment_returns_rows' => false,
        ];
    }

    public function trustedSchema(): bool
    {
        return $this->trustedSchema;
    }

    /**
     * @param list<array{name:string,generated?:string,default?:string}> $columns
     * @param list<string> $checks
     * @return array{status:string,schema:string,table:string,column_count:int,check_count:int}
     */
    public function createTable(string $schema, string $name, array $columns, array $checks = []): array
    {
        $schema = self::schema($schema);
        $name = self::identifier($name, 'SQLite table name');
        $normalizedColumns = [];
        foreach ($columns as $column) {
            $columnName = self::identifier((string) ($column['name'] ?? ''), 'SQLite column name');
            $normalized = ['name' => $columnName];
            if (array_key_exists('generated', $column)) {
                $expression = trim((string) $column['generated']);
                $this->assertSchemaExpressionAllowed($schema, $expression);
                $normalized['generated'] = $expression;
            }
            if (array_key_exists('default', $column)) {
                $normalized['default'] = trim((string) $column['default']);
            }
            $normalizedColumns[] = $normalized;
        }

        $normalizedChecks = [];
        foreach ($checks as $check) {
            $expression = trim($check);
            $this->assertSchemaExpressionAllowed($schema, $expression);
            $normalizedChecks[] = $expression;
        }

        $this->ensureSchema($schema);
        $this->tables[$schema][$name] = [
            'columns' => $normalizedColumns,
            'checks' => $normalizedChecks,
            'rows' => [],
        ];

        return [
            'status' => 'ok',
            'schema' => $schema,
            'table' => $name,
            'column_count' => count($normalizedColumns),
            'check_count' => count($normalizedChecks),
        ];
    }

    /**
     * @param array<string,int|string|null> $row
     * @return array{status:string,schema:string,table:string,row_count:int,row:array<string,int|string|null>}
     */
    public function insert(string $schema, string $table, array $row): array
    {
        $schema = self::schema($schema);
        $table = self::identifier($table, 'SQLite table name');
        $this->ensureTable($schema, $table);
        $record = $this->tables[$schema][$table];
        $next = [];

        foreach ($record['columns'] as $column) {
            $name = $column['name'];
            if (array_key_exists($name, $row)) {
                $next[$name] = $row[$name];
                continue;
            }
            if (isset($column['generated'])) {
                $this->assertSchemaExpressionAllowed($schema, $column['generated']);
                $next[$name] = $this->evaluateExpression($column['generated'], $next + $row);
                continue;
            }
            if (isset($column['default'])) {
                $this->assertSchemaExpressionAllowed($schema, $column['default']);
                $next[$name] = $this->evaluateExpression($column['default'], $next + $row);
                continue;
            }
            $next[$name] = null;
        }

        foreach ($record['checks'] as $check) {
            $this->assertSchemaExpressionAllowed($schema, $check);
            if (!$this->truthy($this->evaluateExpression($check, $next))) {
                throw new RuntimeException('CHECK constraint failed');
            }
        }

        $this->tables[$schema][$table]['rows'][] = $next;
        foreach ($this->triggers[$schema] ?? [] as $trigger) {
            if ($trigger['table'] !== $table) {
                continue;
            }
            $this->assertSchemaExpressionAllowed($schema, $trigger['expression']);
            $next[$trigger['target']] = $this->evaluateExpression($trigger['expression'], $next);
        }

        return [
            'status' => 'ok',
            'schema' => $schema,
            'table' => $table,
            'row_count' => count($this->tables[$schema][$table]['rows']),
            'row' => $next,
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<array<string,int|string|null>>
     */
    public function selectTable(string $schema, string $table, array $columns): array
    {
        $schema = self::schema($schema);
        $table = self::identifier($table, 'SQLite table name');
        $this->ensureTable($schema, $table);

        $columnMap = [];
        foreach ($this->tables[$schema][$table]['columns'] as $column) {
            $columnMap[$column['name']] = $column;
        }

        $rows = [];
        foreach ($this->tables[$schema][$table]['rows'] as $row) {
            $out = [];
            foreach ($columns as $columnName) {
                $columnName = self::identifier($columnName, 'SQLite column name');
                $column = $columnMap[$columnName] ?? null;
                if ($column === null) {
                    throw new RuntimeException("no such column: {$columnName}");
                }
                if (isset($column['generated'])) {
                    $this->assertSchemaExpressionAllowed($schema, $column['generated']);
                    $out[$columnName] = $this->evaluateExpression($column['generated'], $row);
                } else {
                    $out[$columnName] = $row[$columnName] ?? null;
                }
            }
            $rows[] = $out;
        }

        return $rows;
    }

    /**
     * @param list<string> $expressions
     * @return array{status:string,schema:string,index:string,table:string,expression_count:int,where:string|null}
     */
    public function createIndex(string $schema, string $name, string $table, array $expressions, ?string $where = null): array
    {
        $schema = self::schema($schema);
        $name = self::identifier($name, 'SQLite index name');
        $table = self::identifier($table, 'SQLite table name');
        $this->ensureTable($schema, $table);
        $normalizedExpressions = [];
        foreach ($expressions as $expression) {
            $expression = trim($expression);
            $this->assertSchemaExpressionAllowed($schema, $expression);
            $normalizedExpressions[] = $expression;
        }
        if ($where !== null) {
            $where = trim($where);
            $this->assertSchemaExpressionAllowed($schema, $where);
        }

        $this->indexes[$schema][$name] = [
            'table' => $table,
            'expressions' => $normalizedExpressions,
            'where' => $where,
        ];

        return [
            'status' => 'ok',
            'schema' => $schema,
            'index' => $name,
            'table' => $table,
            'expression_count' => count($normalizedExpressions),
            'where' => $where,
        ];
    }

    /**
     * @return list<array<string,int|string|null>>
     */
    public function queryUsingIndex(string $schema, string $indexName, string $predicateExpression): array
    {
        $schema = self::schema($schema);
        $indexName = self::identifier($indexName, 'SQLite index name');
        $index = $this->indexes[$schema][$indexName] ?? null;
        if ($index === null) {
            throw new RuntimeException("no such index: {$indexName}");
        }

        foreach ($index['expressions'] as $expression) {
            $this->assertSchemaExpressionAllowed($schema, $expression);
        }
        if ($index['where'] !== null) {
            $this->assertSchemaExpressionAllowed($schema, $index['where']);
        }
        $this->assertSchemaExpressionAllowed($schema, $predicateExpression);

        $rows = [];
        foreach ($this->tables[$schema][$index['table']]['rows'] as $row) {
            if ($this->truthy($this->evaluateExpression($predicateExpression, $row))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array{alias:string,expression:string}> $projections
     * @return array{status:string,schema:string,view:string,projection_count:int}
     */
    public function createView(string $schema, string $name, string $sourceTable, array $projections): array
    {
        $schema = self::schema($schema);
        $name = self::identifier($name, 'SQLite view name');
        $sourceTable = self::identifier($sourceTable, 'SQLite view source table');
        $this->ensureTable('main', $sourceTable);
        $normalized = [];
        foreach ($projections as $projection) {
            $normalized[] = [
                'alias' => self::identifier((string) $projection['alias'], 'SQLite view column alias'),
                'expression' => trim((string) $projection['expression']),
            ];
        }

        $this->ensureSchema($schema);
        $this->views[$schema][$name] = [
            'source' => $sourceTable,
            'projections' => $normalized,
        ];

        return [
            'status' => 'ok',
            'schema' => $schema,
            'view' => $name,
            'projection_count' => count($normalized),
        ];
    }

    /**
     * @return list<array<string,int|string|null>>
     */
    public function selectView(string $schema, string $name): array
    {
        $schema = self::schema($schema);
        $name = self::identifier($name, 'SQLite view name');
        $view = $this->views[$schema][$name] ?? null;
        if ($view === null) {
            throw new RuntimeException("no such view: {$name}");
        }

        $rows = [];
        foreach ($this->tables['main'][$view['source']]['rows'] as $sourceRow) {
            $out = [];
            foreach ($view['projections'] as $projection) {
                $this->assertSchemaExpressionAllowed($schema, $projection['expression']);
                $out[$projection['alias']] = $this->evaluateExpression($projection['expression'], $sourceRow);
            }
            $rows[] = $out;
        }

        return $rows;
    }

    /**
     * @return array{status:string,schema:string,trigger:string,table:string,target:string}
     */
    public function createTrigger(string $schema, string $name, string $table, string $expression, string $target): array
    {
        $schema = self::schema($schema);
        $name = self::identifier($name, 'SQLite trigger name');
        $table = self::identifier($table, 'SQLite trigger table');
        $target = self::identifier($target, 'SQLite trigger target');
        $this->ensureTable('main', $table);
        $this->ensureSchema($schema);
        $this->triggers[$schema][$name] = [
            'table' => $table,
            'expression' => trim($expression),
            'target' => $target,
        ];

        return [
            'status' => 'ok',
            'schema' => $schema,
            'trigger' => $name,
            'table' => $table,
            'target' => $target,
        ];
    }

    /**
     * @return int|string|null
     */
    public function directSelectExpression(string $expression, array $row = []): int|string|null
    {
        return $this->evaluateExpression($expression, $row);
    }

    /**
     * @return array{schema:string,value:bool|null}
     */
    public static function parseTrustedSchemaPragma(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $value = 'ON|OFF|YES|NO|TRUE|FALSE|[+-]?\d+';
        if (preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?trusted_schema(?:\s*(?:=\s*(?<equals>' . $value . ')|\(\s*(?<paren>' . $value . ')\s*\)))?$/i', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('Unsupported SQLite trusted_schema PRAGMA SQL');
        }

        $raw = null;
        if (($matches['equals'] ?? '') !== '') {
            $raw = $matches['equals'];
        } elseif (($matches['paren'] ?? '') !== '') {
            $raw = $matches['paren'];
        }

        return [
            'schema' => strtolower(($matches['schema'] ?? '') !== '' ? $matches['schema'] : 'main'),
            'value' => $raw === null ? null : self::boolValue($raw),
        ];
    }

    private function assertSchemaExpressionAllowed(string $schema, string $expression): void
    {
        foreach ($this->functionNames($expression) as $function) {
            $metadata = $this->functions[$function] ?? null;
            if ($metadata === null) {
                throw new RuntimeException("no such function: {$function}");
            }
            if ($schema === 'temp') {
                continue;
            }
            if ($metadata['direct_only']) {
                throw new RuntimeException("unsafe use of {$function}()");
            }
            if (!$this->trustedSchema && !$metadata['innocuous']) {
                throw new RuntimeException("unsafe use of {$function}()");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function functionNames(string $expression): array
    {
        if (preg_match_all('/\b(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*\(/', $expression, $matches) !== 1) {
            return [];
        }

        $names = [];
        foreach ($matches['name'] as $name) {
            $names[] = strtolower($name);
        }

        return array_values(array_unique($names));
    }

    /**
     * @return int|string|null
     */
    private function evaluateExpression(string $expression, array $row): int|string|null
    {
        $expression = trim($expression);
        if ($expression === '') {
            return null;
        }
        if (preg_match('/^json_extract\s*\(\s*\'\{"a":(?<value>-?\d+)\}\'\s*,\s*\'\$\.a\'\s*\)$/i', $expression, $matches) === 1) {
            return (int) $matches['value'];
        }
        if (preg_match('/^(?<left>.+?)\s*==\s*(?<right>.+)$/', $expression, $matches) === 1) {
            return $this->evaluateExpression($matches['left'], $row) === $this->evaluateExpression($matches['right'], $row) ? 1 : 0;
        }
        if (preg_match('/^(?<function>[A-Za-z_][A-Za-z0-9_]*)\s*\((?<inner>.*)\)$/', $expression, $matches) === 1) {
            $function = strtolower($matches['function']);
            if (!isset($this->functions[$function])) {
                throw new RuntimeException("no such function: {$function}");
            }

            return $this->evaluateExpression($matches['inner'], $row);
        }
        if (preg_match('/^[+-]?\d+$/', $expression) === 1) {
            return (int) $expression;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return $row[strtolower($expression)] ?? null;
        }

        $parts = self::splitAdditive($expression);
        if (count($parts) > 1) {
            $sum = 0;
            foreach ($parts as $part) {
                $value = $this->evaluateExpression($part, $row);
                $sum += (int) $value;
            }

            return $sum;
        }

        return trim($expression, "'\"");
    }

    /**
     * @return list<string>
     */
    private static function splitAdditive(string $expression): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char !== '+' || $depth !== 0) {
                continue;
            }
            $parts[] = trim(substr($expression, $start, $i - $start));
            $start = $i + 1;
        }
        $parts[] = trim(substr($expression, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function truthy(int|string|null $value): bool
    {
        return $value !== null && $value !== 0 && $value !== '0' && $value !== '';
    }

    private function ensureSchema(string $schema): void
    {
        $this->tables[$schema] ??= [];
        $this->indexes[$schema] ??= [];
        $this->views[$schema] ??= [];
        $this->triggers[$schema] ??= [];
    }

    private function ensureTable(string $schema, string $table): void
    {
        $this->ensureSchema($schema);
        if (!isset($this->tables[$schema][$table])) {
            throw new RuntimeException("no such table: {$schema}.{$table}");
        }
    }

    private static function schema(string $schema): string
    {
        return self::identifier(strtolower($schema), 'SQLite schema name');
    }

    private static function identifier(string $value, string $label): string
    {
        $identifier = strtolower(trim($value));
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("{$label} is invalid");
        }

        return $identifier;
    }

    private static function boolValue(bool|int|string $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        $upper = strtoupper(trim($value));

        return match ($upper) {
            'ON', 'YES', 'TRUE' => true,
            'OFF', 'NO', 'FALSE' => false,
            default => self::intBool($upper),
        };
    }

    private static function intBool(string $value): bool
    {
        if (!preg_match('/^[+-]?\d+$/', $value)) {
            throw new InvalidArgumentException('SQLite trusted_schema PRAGMA value must be boolean-like');
        }

        return (int) $value !== 0;
    }
}
