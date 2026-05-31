<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteReturningDdlErrorPlan
{
    /**
     * @return array{source:string,scenario:string,view:string,trigger:string,returning:string,error:string,changes:int,trigger_body_ran:bool,dependencies:list<string>}
     */
    public static function insertIntoViewWithReturningCollationError(string $view, string $trigger, string $collation): array
    {
        self::identifier($view, 'view');
        self::identifier($trigger, 'trigger');
        self::identifier($collation, 'collation');

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-18.1 INSERT INTO view DEFAULT VALUES RETURNING * reports invalid collation before trigger body effects',
            'view' => $view,
            'trigger' => $trigger,
            'returning' => '*',
            'error' => 'no such collation sequence: ' . $collation,
            'changes' => 0,
            'trigger_body_ran' => false,
            'dependencies' => [
                'sqlite-returning-view-trigger-error-order',
                'returning1.test-18.1',
            ],
        ];
    }

    /**
     * @return array{source:string,scenario:string,table:string,trigger:string,created:bool,error:null,validated_returning_body:bool,body_statements:list<string>,dependencies:list<string>}
     */
    public static function createTriggerIfNotExistsSkipsReturningBodyValidation(string $table, string $trigger): array
    {
        self::identifier($table, 'table');
        self::identifier($trigger, 'trigger');

        return [
            'source' => 'returning1.test',
            'scenario' => 'returning1-19.1 CREATE TRIGGER IF NOT EXISTS skips validation of duplicate trigger RETURNING body',
            'table' => $table,
            'trigger' => $trigger,
            'created' => false,
            'error' => null,
            'validated_returning_body' => false,
            'body_statements' => [
                'INSERT INTO ' . $table . '(a) VALUES (1) RETURNING FALSE',
                'INSERT INTO ' . $table . '(a) VALUES (2) RETURNING TRUE',
            ],
            'dependencies' => [
                'sqlite-returning-trigger-if-not-exists-short-circuit',
                'returning1.test-19.1',
            ],
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite RETURNING {$label} identifier is malformed");
        }

        return $value;
    }
}
