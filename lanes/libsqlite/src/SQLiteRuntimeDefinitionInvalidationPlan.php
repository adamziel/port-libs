<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRuntimeDefinitionInvalidationPlan
{
    /**
     * @param list<array{name:string,sql:string,active?:bool,read_only?:bool,uses?:list<string>}> $preparedStatements
     * @param list<array{op:string,kind:string,name?:string}> $events
     * @return array<string,mixed>
     */
    public static function plan(array $preparedStatements, array $events): array
    {
        if ($preparedStatements === []) {
            throw new \InvalidArgumentException('SQLite runtime definition invalidation requires prepared statements');
        }

        $statements = [];
        foreach ($preparedStatements as $index => $statement) {
            $name = self::statementName($statement, $index);
            $statements[$name] = [
                'name' => $name,
                'sql' => self::nonEmptyString($statement['sql'] ?? '', 'SQLite prepared SQL'),
                'active' => (bool) ($statement['active'] ?? false),
                'read_only' => $statement['read_only'] ?? self::readOnly((string) $statement['sql']),
                'uses' => self::uses($statement),
            ];
        }

        $log = [];
        $invalidateAll = false;
        $deletedFunctions = [];
        $deletedCollations = [];
        $addedDefinitions = [];
        $blockedDefinitions = [];

        foreach ($events as $index => $event) {
            $kind = self::definitionKind((string) ($event['kind'] ?? ''));
            $op = self::operation((string) ($event['op'] ?? ''));
            $definition = $kind === 'authorizer' ? 'authorizer' : self::definitionName((string) ($event['name'] ?? ''), $kind);
            $activeStatements = self::activeStatementNames($statements);

            $invalidates = false;
            $sqliteResult = 'SQLITE_OK';
            $message = null;

            if ($kind === 'authorizer') {
                $invalidates = true;
                $invalidateAll = true;
            } elseif ($op === 'delete' || $op === 'replace') {
                if ($activeStatements !== []) {
                    $sqliteResult = 'SQLITE_BUSY';
                    $message = $kind === 'function'
                        ? 'unable to delete/modify user-function due to active statements'
                        : 'unable to delete/modify collation sequence due to active statements';
                    $blockedDefinitions[] = $kind . ':' . $definition;
                } else {
                    $invalidates = true;
                    if ($kind === 'function') {
                        $deletedFunctions[] = $definition;
                    } else {
                        $deletedCollations[] = $definition;
                    }
                }
            } else {
                $addedDefinitions[] = $kind . ':' . $definition;
            }

            $log[] = [
                'index' => $index,
                'op' => $op,
                'kind' => $kind,
                'name' => $definition,
                'active_statements' => $activeStatements,
                'invalidates_prepared_statements' => $invalidates,
                'sqlite_result' => $sqliteResult,
                'message' => $message,
            ];
        }

        $expired = [];
        $stable = [];
        $statementPlans = [];
        foreach ($statements as $statement) {
            $reasons = [];
            if ($invalidateAll) {
                $reasons[] = 'authorizer-change';
            }
            foreach ($statement['uses'] as $use) {
                if (str_starts_with($use, 'function:') && in_array(substr($use, 9), $deletedFunctions, true)) {
                    $reasons[] = 'user-function-delete';
                }
                if (str_starts_with($use, 'collation:') && in_array(substr($use, 10), $deletedCollations, true)) {
                    $reasons[] = 'collation-delete';
                }
            }
            $reasons = array_values(array_unique($reasons));
            $requiresReprepare = $reasons !== [];
            if ($requiresReprepare) {
                $expired[] = $statement['name'];
            } else {
                $stable[] = $statement['name'];
            }

            $statementPlans[] = [
                'name' => $statement['name'],
                'sql' => $statement['sql'],
                'active' => $statement['active'],
                'read_only' => $statement['read_only'],
                'uses' => $statement['uses'],
                'invalidation_reasons' => $reasons,
                'requires_reprepare' => $requiresReprepare,
                'sqlite_result_on_current_step' => $statement['active'] ? 'SQLITE_OK' : ($requiresReprepare ? 'SQLITE_SCHEMA' : 'SQLITE_OK'),
                'next_step_action' => self::nextStep($requiresReprepare, $statement['active'], $statement['read_only']),
            ];
        }

        sort($deletedFunctions);
        sort($deletedCollations);
        sort($addedDefinitions);
        $blockedDefinitions = array_values(array_unique($blockedDefinitions));
        sort($blockedDefinitions);

        return [
            'status' => $expired === [] ? 'runtime_definitions_stable' : 'runtime_definitions_expired',
            'operation' => 'runtime-definition-invalidation',
            'event_count' => count($events),
            'statement_count' => count($statements),
            'events' => $log,
            'statements' => $statementPlans,
            'expired_statements' => $expired,
            'stable_statements' => $stable,
            'deleted_functions' => $deletedFunctions,
            'deleted_collations' => $deletedCollations,
            'added_definitions' => $addedDefinitions,
            'blocked_definitions' => $blockedDefinitions,
            'requires_reprepare' => $expired !== [],
            'dependencies' => [
                'sqlite-runtime-definition-invalidation',
                'sqlite-prepared-statement-active-definition-busy',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $statement
     * @return list<string>
     */
    private static function uses(array $statement): array
    {
        $uses = [];
        foreach ($statement['uses'] ?? [] as $use) {
            $parts = explode(':', (string) $use, 2);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException('SQLite runtime statement use must be kind:name');
            }
            $kind = self::definitionKind($parts[0]);
            if ($kind === 'authorizer') {
                $uses[] = 'authorizer:authorizer';
            } else {
                $uses[] = $kind . ':' . self::definitionName($parts[1], $kind);
            }
        }

        return array_values(array_unique($uses));
    }

    private static function nextStep(bool $requiresReprepare, bool $active, bool $readOnly): string
    {
        if (!$requiresReprepare) {
            return 'reuse_prepared_statement';
        }
        if ($active) {
            return 'finish_current_source_then_reprepare_on_reset';
        }

        return $readOnly ? 'sqlite_schema_then_reprepare_and_retry' : 'sqlite_schema_before_write_retry';
    }

    private static function operation(string $op): string
    {
        $normalized = strtolower(trim($op));
        if (!in_array($normalized, ['add', 'delete', 'replace', 'set'], true)) {
            throw new \InvalidArgumentException("SQLite runtime definition operation {$op} is not supported");
        }

        return $normalized;
    }

    private static function definitionKind(string $kind): string
    {
        $normalized = strtolower(trim($kind));
        if (!in_array($normalized, ['function', 'collation', 'authorizer'], true)) {
            throw new \InvalidArgumentException("SQLite runtime definition kind {$kind} is not supported");
        }

        return $normalized;
    }

    private static function definitionName(string $name, string $kind): string
    {
        $trimmed = strtolower(trim($name));
        if ($trimmed === '') {
            throw new \InvalidArgumentException("SQLite {$kind} name cannot be empty");
        }

        return $trimmed;
    }

    /**
     * @param array<string,array{name:string,active:bool}> $statements
     * @return list<string>
     */
    private static function activeStatementNames(array $statements): array
    {
        $active = [];
        foreach ($statements as $statement) {
            if ($statement['active']) {
                $active[] = $statement['name'];
            }
        }

        return $active;
    }

    /**
     * @param array{name?:string} $statement
     */
    private static function statementName(array $statement, int $index): string
    {
        $name = trim((string) ($statement['name'] ?? 'stmt-' . $index));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite prepared statement name cannot be empty');
        }

        return $name;
    }

    private static function nonEmptyString(mixed $value, string $label): string
    {
        $string = trim((string) $value);
        if ($string === '') {
            throw new \InvalidArgumentException($label . ' cannot be empty');
        }

        return $string;
    }

    private static function readOnly(string $sql): bool
    {
        $keyword = strtoupper(strtok(ltrim($sql), " \t\r\n(") ?: '');

        return in_array($keyword, ['SELECT', 'PRAGMA'], true);
    }
}
