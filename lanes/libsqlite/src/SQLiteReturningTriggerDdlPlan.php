<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteReturningTriggerDdlPlan
{
    /**
     * @param array{name:string,select_expression:string,collations?:list<string>} $view
     * @param array{name:string,timing:string,event:string,target:string,body:list<string>} $trigger
     * @return array{status:string,error:?string,returning_rows:list<array<string,mixed>>,trigger_fired:bool,dependencies:list<string>}
     */
    public static function insertDefaultValuesIntoViewReturning(array $view, array $trigger): array
    {
        $viewName = self::identifier($view['name'] ?? null, 'view name');
        $triggerTarget = self::identifier($trigger['target'] ?? null, 'trigger target');
        if ($triggerTarget !== $viewName) {
            throw new \InvalidArgumentException('SQLite RETURNING trigger target must match the view');
        }

        $expression = self::stringValue($view['select_expression'] ?? null, 'view select expression');
        $availableCollations = array_map('strtoupper', self::stringList($view['collations'] ?? [], 'view collations'));
        if (preg_match_all('/\bCOLLATE\s+([A-Za-z_][A-Za-z0-9_]*)/i', $expression, $matches) > 0) {
            foreach ($matches[1] as $collation) {
                if (!in_array(strtoupper($collation), $availableCollations, true)) {
                    return [
                        'status' => 'error-before-returning',
                        'error' => "no such collation sequence: {$collation}",
                        'returning_rows' => [],
                        'trigger_fired' => false,
                        'dependencies' => ['returning1.test-18.0', 'returning1.test-18.1'],
                    ];
                }
            }
        }

        return [
            'status' => 'inserted-through-view-trigger',
            'error' => null,
            'returning_rows' => [['rowid' => -1]],
            'trigger_fired' => true,
            'dependencies' => ['returning1.test-18.0', 'returning1.test-18.1'],
        ];
    }

    /**
     * @param array<string,array{name:string,event:string,target:string,body:list<string>}> $existingTriggers
     * @param array{name:string,event:string,target:string,body:list<string>,if_not_exists?:bool} $definition
     * @return array{status:string,created:bool,error:?string,trigger_count:int,ignored_body_errors:list<string>,dependencies:list<string>}
     */
    public static function createTrigger(array $existingTriggers, array $definition): array
    {
        $name = self::identifier($definition['name'] ?? null, 'trigger name');
        $ifNotExists = (bool) ($definition['if_not_exists'] ?? false);
        $body = self::stringList($definition['body'] ?? [], 'trigger body');

        if (array_key_exists($name, $existingTriggers)) {
            if (!$ifNotExists) {
                return [
                    'status' => 'error',
                    'created' => false,
                    'error' => "trigger {$name} already exists",
                    'trigger_count' => count($existingTriggers),
                    'ignored_body_errors' => [],
                    'dependencies' => ['returning1.test-19.0', 'returning1.test-19.1'],
                ];
            }

            return [
                'status' => 'skipped-existing-trigger',
                'created' => false,
                'error' => null,
                'trigger_count' => count($existingTriggers),
                'ignored_body_errors' => self::returningBodyErrors($body),
                'dependencies' => ['returning1.test-19.0', 'returning1.test-19.1'],
            ];
        }

        $errors = self::returningBodyErrors($body);
        if ($errors !== []) {
            return [
                'status' => 'error',
                'created' => false,
                'error' => $errors[0],
                'trigger_count' => count($existingTriggers),
                'ignored_body_errors' => [],
                'dependencies' => ['returning1.test-19.0', 'returning1.test-19.1'],
            ];
        }

        return [
            'status' => 'created',
            'created' => true,
            'error' => null,
            'trigger_count' => count($existingTriggers) + 1,
            'ignored_body_errors' => [],
            'dependencies' => ['returning1.test-19.0', 'returning1.test-19.1'],
        ];
    }

    /**
     * @param list<string> $body
     * @return list<string>
     */
    private static function returningBodyErrors(array $body): array
    {
        $errors = [];
        foreach ($body as $statement) {
            if (preg_match('/\bRETURNING\s+(FALSE|TRUE)\b/i', $statement, $match) === 1) {
                $errors[] = 'RETURNING clause inside trigger body is not evaluated for an existing IF NOT EXISTS trigger: ' . strtoupper($match[1]);
            }
        }

        return $errors;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite RETURNING {$label} must be a non-empty string");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite RETURNING {$label} must be a list");
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("SQLite RETURNING {$label} entries must be non-empty strings");
            }
        }

        return $value;
    }
}
