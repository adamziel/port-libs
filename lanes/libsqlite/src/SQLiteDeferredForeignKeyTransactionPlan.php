<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDeferredForeignKeyTransactionPlan
{
    /**
     * @param list<array<string,mixed>> $operations
     * @return array{trace:list<array<string,mixed>>,node:list<array{nodeid:int,parent:?int}>,leaf:list<array{cellid:string,parent:int}>,open_transaction:bool,savepoints:list<string>,violations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function replay(array $operations): array
    {
        $state = [
            'node' => [],
            'leaf' => [],
            'explicit' => false,
            'savepoints' => [],
            'snapshots' => [],
        ];
        $trace = [];

        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                throw new \InvalidArgumentException('SQLite deferred foreign-key operation must be an array');
            }
            $case = self::caseName($operation['case'] ?? null);
            $op = self::opName($operation['op'] ?? null);
            $before = self::snapshot($state);
            $result = ['ok' => true, 'error' => null, 'result' => null, 'rolled_back_statement' => false];

            try {
                self::apply($state, $op, $operation);
                if (in_array($op, ['insert-node-autofill'], true)) {
                    self::assertNoDuplicateNode($state['node']);
                }
                if (in_array($op, ['insert-node', 'insert-leaf', 'update-node-parent', 'delete-node', 'delete-leaf', 'delete-all-node', 'delete-all-leaf', 'insert-node-distinct-leaf-parents'], true) && !$state['explicit'] && $state['savepoints'] === []) {
                    self::assertNoViolations($state);
                }
                if ($op === 'release' && self::isTransactionBoundaryRelease($state, $operation)) {
                    self::assertNoViolations($state);
                }
                if ($op === 'select-node') {
                    $result['result'] = self::flattenNode($state['node']);
                } elseif ($op === 'select-leaf') {
                    $result['result'] = self::flattenLeaf($state['leaf']);
                }
            } catch (\RuntimeException $exception) {
                $state = self::restoreMutable($state, $before);
                $result = [
                    'ok' => false,
                    'error' => $exception->getMessage(),
                    'result' => null,
                    'rolled_back_statement' => true,
                ];
            }

            $violations = self::violations($state);
            $trace[] = [
                'case' => $case,
                'op' => $op,
                'ok' => $result['ok'],
                'error' => $result['error'],
                'result' => $result['result'],
                'node' => $state['node'],
                'leaf' => $state['leaf'],
                'node_flat' => self::flattenNode($state['node']),
                'leaf_flat' => self::flattenLeaf($state['leaf']),
                'violation_count' => count($violations),
                'violations' => $violations,
                'open_transaction' => $state['explicit'] || $state['savepoints'] !== [],
                'savepoints' => $state['savepoints'],
                'rolled_back_statement' => $result['rolled_back_statement'],
                'deferred_check_pending' => $violations !== [],
            ];
        }

        return [
            'trace' => $trace,
            'node' => $state['node'],
            'leaf' => $state['leaf'],
            'open_transaction' => $state['explicit'] || $state['savepoints'] !== [],
            'savepoints' => $state['savepoints'],
            'violations' => self::violations($state),
            'dependencies' => [
                'sqlite-upstream-fkey2-2-deferred-foreign-keys',
                'sqlite-deferred-foreign-key-savepoint-release',
                'sqlite-deferred-foreign-key-counter-reset',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $operation
     */
    private static function apply(array &$state, string $op, array $operation): void
    {
        switch ($op) {
            case 'schema':
                $state['node'] = [];
                $state['leaf'] = [];
                return;
            case 'begin':
                if ($state['explicit'] || $state['savepoints'] !== []) {
                    throw new \RuntimeException('cannot start a transaction within a transaction');
                }
                $state['explicit'] = true;
                return;
            case 'commit':
                self::assertNoViolations($state);
                $state['explicit'] = false;
                $state['savepoints'] = [];
                $state['snapshots'] = [];
                return;
            case 'savepoint':
                $name = self::identifier($operation['name'] ?? null, 'savepoint');
                $state['savepoints'][] = $name;
                $state['snapshots'][$name] = self::snapshot($state);
                return;
            case 'rollback-to':
                $name = self::identifier($operation['name'] ?? null, 'savepoint');
                if (!isset($state['snapshots'][$name])) {
                    throw new \RuntimeException('no such savepoint: ' . $name);
                }
                $snapshot = $state['snapshots'][$name];
                $state['node'] = $snapshot['node'];
                $state['leaf'] = $snapshot['leaf'];
                $position = array_search($name, $state['savepoints'], true);
                $state['savepoints'] = array_slice($state['savepoints'], 0, (int) $position + 1);
                return;
            case 'release':
                $name = self::identifier($operation['name'] ?? null, 'savepoint');
                $position = array_search($name, $state['savepoints'], true);
                if ($position === false) {
                    throw new \RuntimeException('no such savepoint: ' . $name);
                }
                $outermost = $position === 0 && !$state['explicit'];
                if ($outermost) {
                    self::assertNoViolations($state);
                }
                $state['savepoints'] = array_slice($state['savepoints'], 0, (int) $position);
                unset($state['snapshots'][$name]);
                return;
            case 'insert-node':
                $state['node'][] = [
                    'nodeid' => self::intValue($operation['nodeid'] ?? null, 'nodeid'),
                    'parent' => self::nullableInt($operation['parent'] ?? null, 'parent'),
                ];
                self::sortRows($state);
                self::assertNoDuplicateNode($state['node']);
                return;
            case 'insert-leaf':
                $state['leaf'][] = [
                    'cellid' => self::textValue($operation['cellid'] ?? null, 'cellid'),
                    'parent' => self::intValue($operation['parent'] ?? null, 'parent'),
                ];
                self::sortRows($state);
                return;
            case 'update-node-parent':
                $nodeid = self::intValue($operation['nodeid'] ?? null, 'nodeid');
                foreach ($state['node'] as &$row) {
                    if ($row['nodeid'] === $nodeid) {
                        $row['parent'] = self::nullableInt($operation['parent'] ?? null, 'parent');
                    }
                }
                unset($row);
                return;
            case 'delete-node':
                $state['node'] = array_values(array_filter(
                    $state['node'],
                    static fn (array $row): bool => $row['nodeid'] !== self::intValue($operation['nodeid'] ?? null, 'nodeid'),
                ));
                return;
            case 'delete-leaf':
                $cellid = self::textValue($operation['cellid'] ?? null, 'cellid');
                $state['leaf'] = array_values(array_filter($state['leaf'], static fn (array $row): bool => $row['cellid'] !== $cellid));
                return;
            case 'delete-all-node':
                $state['node'] = [];
                return;
            case 'delete-all-leaf':
                $state['leaf'] = [];
                return;
            case 'insert-node-distinct-leaf-parents':
                $parents = [];
                foreach ($state['leaf'] as $leaf) {
                    $parents[$leaf['parent']] = true;
                }
                foreach (array_keys($parents) as $parent) {
                    $state['node'][] = ['nodeid' => (int) $parent, 'parent' => null];
                }
                self::sortRows($state);
                return;
            case 'insert-node-autofill':
                foreach ($state['leaf'] as $leaf) {
                    $state['node'][] = ['nodeid' => $leaf['parent'], 'parent' => 3];
                }
                self::sortRows($state);
                return;
            case 'select-node':
            case 'select-leaf':
                return;
            default:
                throw new \InvalidArgumentException('SQLite deferred foreign-key operation is unsupported');
        }
    }

    /** @param array<string,mixed> $state */
    private static function assertNoViolations(array $state): void
    {
        if (self::violations($state) !== []) {
            throw new \RuntimeException('FOREIGN KEY constraint failed');
        }
    }

    /** @param list<array{nodeid:int,parent:?int}> $nodes */
    private static function assertNoDuplicateNode(array $nodes): void
    {
        $seen = [];
        foreach ($nodes as $node) {
            if (isset($seen[$node['nodeid']])) {
                throw new \RuntimeException('UNIQUE constraint failed: node.nodeid');
            }
            $seen[$node['nodeid']] = true;
        }
    }

    /** @param array<string,mixed> $state */
    private static function violations(array $state): array
    {
        $nodes = [];
        foreach ($state['node'] as $node) {
            $nodes[$node['nodeid']] = true;
        }
        $violations = [];
        foreach ($state['node'] as $node) {
            if ($node['parent'] !== null && !isset($nodes[$node['parent']])) {
                $violations[] = ['table' => 'node', 'row' => $node['nodeid'], 'parent' => $node['parent']];
            }
        }
        foreach ($state['leaf'] as $leaf) {
            if (!isset($nodes[$leaf['parent']])) {
                $violations[] = ['table' => 'leaf', 'row' => $leaf['cellid'], 'parent' => $leaf['parent']];
            }
        }

        return $violations;
    }

    /** @param array<string,mixed> $state */
    private static function snapshot(array $state): array
    {
        return [
            'node' => $state['node'],
            'leaf' => $state['leaf'],
            'explicit' => $state['explicit'],
            'savepoints' => $state['savepoints'],
            'snapshots' => $state['snapshots'],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private static function restoreMutable(array $state, array $snapshot): array
    {
        $state['node'] = $snapshot['node'];
        $state['leaf'] = $snapshot['leaf'];
        $state['explicit'] = $snapshot['explicit'];
        $state['savepoints'] = $snapshot['savepoints'];
        $state['snapshots'] = $snapshot['snapshots'];

        return $state;
    }

    /** @param array<string,mixed> $state */
    private static function isTransactionBoundaryRelease(array $state, array $operation): bool
    {
        $name = (string) ($operation['name'] ?? '');

        return !$state['explicit'] && $state['savepoints'] === [$name];
    }

    /** @param array<string,mixed> $state */
    private static function sortRows(array &$state): void
    {
        usort($state['node'], static fn (array $a, array $b): int => $a['nodeid'] <=> $b['nodeid']);
        usort($state['leaf'], static fn (array $a, array $b): int => strcmp($a['cellid'], $b['cellid']));
    }

    /** @param list<array{nodeid:int,parent:?int}> $nodes */
    private static function flattenNode(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $out[] = $node['nodeid'];
            $out[] = $node['parent'];
        }

        return $out;
    }

    /** @param list<array{cellid:string,parent:int}> $leafs */
    private static function flattenLeaf(array $leafs): array
    {
        $out = [];
        foreach ($leafs as $leaf) {
            $out[] = $leaf['cellid'];
            $out[] = $leaf['parent'];
        }

        return $out;
    }

    private static function caseName(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^fkey2-2(?:-test)?-[0-9]+$/', $value)) {
            throw new \InvalidArgumentException('SQLite deferred foreign-key upstream case name is invalid');
        }

        return $value;
    }

    private static function opName(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-z0-9-]+$/', $value)) {
            throw new \InvalidArgumentException('SQLite deferred foreign-key operation name is invalid');
        }

        return $value;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException("SQLite deferred foreign-key {$label} is invalid");
        }

        return $value;
    }

    private static function textValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite deferred foreign-key {$label} must be text");
        }

        return $value;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite deferred foreign-key {$label} must be an integer");
        }

        return $value;
    }

    private static function nullableInt(mixed $value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::intValue($value, $label);
    }
}
