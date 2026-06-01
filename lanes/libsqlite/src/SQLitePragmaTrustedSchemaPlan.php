<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaTrustedSchemaPlan
{
    /** @var array<string,array{innocuous:bool,direct_only:bool,builtin:bool}> */
    private const FUNCTIONS = [
        'f1' => ['innocuous' => true, 'direct_only' => false, 'builtin' => false],
        'f2' => ['innocuous' => false, 'direct_only' => false, 'builtin' => false],
        'f3' => ['innocuous' => false, 'direct_only' => true, 'builtin' => false],
        'json_extract' => ['innocuous' => true, 'direct_only' => false, 'builtin' => true],
    ];

    private const OBJECT_KINDS = [
        'check_constraint',
        'default_constraint',
        'direct_sql',
        'expression_index',
        'generated_column',
        'partial_index',
        'trigger',
        'view',
    ];

    /**
     * Model the trusted_schema safety gate for functions referenced from
     * schema text. Direct SQL is not schema text, and TEMP schema objects are
     * allowed to use application-defined functions just as upstream does.
     *
     * @return array{
     *     status:'ok'|'error',
     *     schema:string,
     *     schema_is_temp:bool,
     *     object_kind:string,
     *     phase:string,
     *     function:string,
     *     trusted_schema:bool,
     *     flags:array{innocuous:bool,direct_only:bool,builtin:bool},
     *     reason:string,
     *     error:string|null,
     *     source:string,
     *     dependencies:list<string>
     * }
     */
    public static function functionUse(
        string $function,
        string $schema,
        string $objectKind,
        string $phase,
        bool $trustedSchema
    ): array {
        $name = self::normalizeFunctionName($function);
        $schemaName = self::normalizeSchemaName($schema);
        $kind = self::normalizeObjectKind($objectKind);
        $phase = self::normalizePhase($phase);
        $flags = self::FUNCTIONS[$name];
        $isTemp = $schemaName === 'temp';

        $status = 'ok';
        $reason = 'trusted_schema_allows_schema_function';
        $error = null;

        if ($kind === 'direct_sql') {
            $reason = 'direct_sql_function_call';
        } elseif ($isTemp) {
            $reason = 'temp_schema_allows_application_functions';
        } elseif ($flags['direct_only'] && ($phase !== 'create' || !in_array($kind, ['trigger', 'view'], true))) {
            $status = 'error';
            $reason = 'directonly_function_not_allowed_in_schema';
            $error = self::unsafeMessage($name);
        } elseif ($flags['direct_only'] && in_array($kind, ['trigger', 'view'], true)) {
            $reason = 'directonly_schema_text_creation_deferred_to_runtime';
        } elseif (!$flags['innocuous'] && !$trustedSchema) {
            $status = 'error';
            $reason = 'unsafe_function_requires_trusted_schema';
            $error = self::unsafeMessage($name);
        } elseif ($flags['builtin']) {
            $reason = 'builtin_innocuous_function_allowed';
        } elseif ($flags['innocuous']) {
            $reason = 'innocuous_function_allowed';
        }

        return [
            'status' => $status,
            'schema' => $schemaName,
            'schema_is_temp' => $isTemp,
            'object_kind' => $kind,
            'phase' => $phase,
            'function' => $name,
            'trusted_schema' => $trustedSchema,
            'flags' => $flags,
            'reason' => $reason,
            'error' => $error,
            'source' => 'SQLite test/trustschema1.test trusted_schema schema-function safety',
            'dependencies' => ['sqlite-pragma-trusted-schema-safety'],
        ];
    }

    /**
     * @param list<array{name:string,function?:string}> $columns
     * @param list<array<string,int|string|null>> $rows
     * @param list<string>|null $selectedColumns
     * @return array{status:'ok'|'error', rows:list<array<string,int|string|null>>, unsafe_functions:list<string>, error:string|null, dependencies:list<string>}
     */
    public static function generatedColumnSelect(
        array $columns,
        array $rows,
        bool $trustedSchema,
        string $schema = 'main',
        ?array $selectedColumns = null
    ): array {
        $wanted = $selectedColumns === null
            ? array_map(static fn (array $column): string => (string) $column['name'], $columns)
            : array_values($selectedColumns);
        $unsafe = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if (!in_array($name, $wanted, true) || !isset($column['function'])) {
                continue;
            }

            $gate = self::functionUse((string) $column['function'], $schema, 'generated_column', 'read', $trustedSchema);
            if ($gate['status'] === 'error') {
                $unsafe[] = $gate['function'];
            }
        }

        if ($unsafe !== []) {
            return [
                'status' => 'error',
                'rows' => [],
                'unsafe_functions' => array_values(array_unique($unsafe)),
                'error' => self::unsafeMessage($unsafe[0]),
                'dependencies' => ['sqlite-pragma-trusted-schema-generated-columns'],
            ];
        }

        $projected = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($wanted as $name) {
                $out[$name] = $row[$name] ?? null;
            }
            $projected[] = $out;
        }

        return [
            'status' => 'ok',
            'rows' => $projected,
            'unsafe_functions' => [],
            'error' => null,
            'dependencies' => ['sqlite-pragma-trusted-schema-generated-columns'],
        ];
    }

    /**
     * @return array{status:'ok'|'error', used_default:bool, value:mixed, error:string|null, reason:string, dependencies:list<string>}
     */
    public static function defaultConstraintInsert(
        string $function,
        mixed $explicitValue,
        bool $trustedSchema,
        string $schema = 'main'
    ): array {
        if ($explicitValue !== null) {
            return [
                'status' => 'ok',
                'used_default' => false,
                'value' => $explicitValue,
                'error' => null,
                'reason' => 'explicit_value_bypasses_default_expression',
                'dependencies' => ['sqlite-pragma-trusted-schema-default-constraint'],
            ];
        }

        $gate = self::functionUse($function, $schema, 'default_constraint', 'insert', $trustedSchema);

        return [
            'status' => $gate['status'],
            'used_default' => $gate['status'] === 'ok',
            'value' => $gate['status'] === 'ok' ? 'default:' . $gate['function'] : null,
            'error' => $gate['error'],
            'reason' => $gate['reason'],
            'dependencies' => ['sqlite-pragma-trusted-schema-default-constraint'],
        ];
    }

    /**
     * @param list<array<string,int|string|null>> $rows
     * @return array{status:'ok'|'error', rows:list<array<string,int|string|null>>, error:string|null, dependencies:list<string>}
     */
    public static function viewSelect(
        string $function,
        array $rows,
        bool $trustedSchema,
        string $schema = 'main'
    ): array {
        $gate = self::functionUse($function, $schema, 'view', 'execute', $trustedSchema);
        if ($gate['status'] === 'error') {
            return [
                'status' => 'error',
                'rows' => [],
                'error' => $gate['error'],
                'dependencies' => ['sqlite-pragma-trusted-schema-view'],
            ];
        }

        return [
            'status' => 'ok',
            'rows' => array_values($rows),
            'error' => null,
            'dependencies' => ['sqlite-pragma-trusted-schema-view'],
        ];
    }

    /**
     * @param list<array<string,int|string|null>> $targetRows
     * @return array{status:'ok'|'error', target_rows:list<array<string,int|string|null>>, side_effect_rows:list<array<string,int|string|null>>, error:string|null, dependencies:list<string>}
     */
    public static function triggerInsert(
        string $function,
        array $targetRows,
        bool $trustedSchema,
        string $schema = 'main'
    ): array {
        $gate = self::functionUse($function, $schema, 'trigger', 'fire', $trustedSchema);
        if ($gate['status'] === 'error') {
            return [
                'status' => 'error',
                'target_rows' => [],
                'side_effect_rows' => [],
                'error' => $gate['error'],
                'dependencies' => ['sqlite-pragma-trusted-schema-trigger'],
            ];
        }

        $sideEffects = [];
        foreach ($targetRows as $row) {
            $sideEffects[] = ['x' => $row['a'] ?? null];
        }

        return [
            'status' => 'ok',
            'target_rows' => array_values($targetRows),
            'side_effect_rows' => $sideEffects,
            'error' => null,
            'dependencies' => ['sqlite-pragma-trusted-schema-trigger'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function sourceSections(): array
    {
        return [
            'trustschema1.test 1.100 through 1.160 generated columns and TEMP generated direct-only functions',
            'trustschema1.test 1.200 through 1.320 CHECK and DEFAULT constraints',
            'trustschema1.test 1.400 through 1.540 partial and expression indexes',
            'trustschema1.test 2.100 through 3.131 views and triggers',
            'trustschema1.test 4.1 through 4.2 json_extract remains allowed with trusted_schema OFF or ON',
        ];
    }

    private static function normalizeFunctionName(string $function): string
    {
        $name = strtolower(trim($function));
        if (!isset(self::FUNCTIONS[$name])) {
            throw new InvalidArgumentException("Unsupported SQLite trusted_schema function {$function}");
        }

        return $name;
    }

    private static function normalizeSchemaName(string $schema): string
    {
        $name = strtolower(trim($schema));
        if ($name === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid SQLite schema name {$schema}");
        }

        return $name;
    }

    private static function normalizeObjectKind(string $objectKind): string
    {
        $kind = strtolower(trim($objectKind));
        if (!in_array($kind, self::OBJECT_KINDS, true)) {
            throw new InvalidArgumentException("Unsupported SQLite trusted_schema object kind {$objectKind}");
        }

        return $kind;
    }

    private static function normalizePhase(string $phase): string
    {
        $normalized = strtolower(trim($phase));
        if ($normalized === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $normalized)) {
            throw new InvalidArgumentException("Invalid SQLite trusted_schema phase {$phase}");
        }

        return $normalized;
    }

    private static function unsafeMessage(string $function): string
    {
        return "unsafe use of {$function}()";
    }
}
