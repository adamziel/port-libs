<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertReturningDynamicCorpusPlan
{
    /**
     * @return list<array{upstream:string,source:string,before:list<array<string,mixed>>,incoming:list<array<string,mixed>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,expected:list<array<string,mixed>>,returning:list<array<string,mixed>>,matched:list<string>,changes:int}>
     */
    public static function multiArmConflictCases(): array
    {
        $rows = [
            ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
            ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
            ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
            ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
        ];
        $constraints = [['a'], ['c'], ['d'], ['e']];
        $arms = [
            ['target' => ['a'], 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'a']],
            ['target' => ['c'], 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'c']],
            ['target' => ['d'], 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'd']],
            ['target' => ['e'], 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'e']],
        ];

        $incomingSets = [
            'upsert5-1.1' => [['a' => 1, 'b' => 'incoming', 'c' => 101, 'd' => 101, 'e' => 101]],
            'upsert5-1.2' => [['a' => 9, 'b' => 'incoming', 'c' => 20, 'd' => 101, 'e' => 101]],
            'upsert5-1.3' => [['a' => 9, 'b' => 'incoming', 'c' => 91, 'd' => 300, 'e' => 101]],
            'upsert5-1.4' => [['a' => 9, 'b' => 'incoming', 'c' => 91, 'd' => 901, 'e' => 4000]],
            'upsert5-2.1' => [['a' => 1, 'b' => 'incoming', 'c' => 20, 'd' => 300, 'e' => 4000]],
            'upsert5-2.2' => [['a' => 9, 'b' => 'incoming', 'c' => 20, 'd' => 300, 'e' => 4000]],
            'upsert5-2.3' => [['a' => 9, 'b' => 'incoming', 'c' => 91, 'd' => 300, 'e' => 4000]],
            'upsert5-3.1' => [['a' => 9, 'b' => 'inserted', 'c' => 91, 'd' => 901, 'e' => 9001]],
        ];

        $cases = [];
        foreach ($incomingSets as $upstream => $incoming) {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, $incoming, $arms, $constraints);
            $cases[] = [
                'upstream' => $upstream,
                'source' => 'upsert5.test',
                'before' => $plan['before'],
                'incoming' => $incoming,
                'constraints' => $constraints,
                'arms' => $arms,
                'expected' => $plan['after'],
                'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
                    'a',
                    'selected_arm' => 'b',
                    'conflict_c' => 'c',
                    'conflict_d' => 'd',
                    'conflict_e' => 'e',
                ]),
                'matched' => array_map(
                    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
                    $plan['matched_arms'],
                ),
                'changes' => $plan['changes'],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,order:list<string>,incoming:array<string,mixed>,selected:string,returning:list<array<string,mixed>>,after:list<array<string,mixed>>,matched:list<string>}>
     */
    public static function multiArmOrderCases(): array
    {
        $rows = [
            ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
            ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
            ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
            ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
        ];
        $constraints = [['a'], ['c'], ['d'], ['e']];
        $incoming = ['a' => 1, 'b' => 'incoming', 'c' => 20, 'd' => 300, 'e' => 4000];
        $orders = [
            ['a', 'c', 'd', 'e'],
            ['a', 'c', 'e', 'd'],
            ['a', 'd', 'c', 'e'],
            ['a', 'd', 'e', 'c'],
            ['a', 'e', 'c', 'd'],
            ['a', 'e', 'd', 'c'],
            ['c', 'a', 'd', 'e'],
            ['c', 'a', 'e', 'd'],
            ['c', 'd', 'a', 'e'],
            ['c', 'd', 'e', 'a'],
            ['c', 'e', 'a', 'd'],
            ['c', 'e', 'd', 'a'],
            ['d', 'a', 'c', 'e'],
            ['d', 'a', 'e', 'c'],
            ['d', 'c', 'a', 'e'],
            ['d', 'c', 'e', 'a'],
            ['d', 'e', 'a', 'c'],
            ['d', 'e', 'c', 'a'],
            ['e', 'a', 'c', 'd'],
            ['e', 'a', 'd', 'c'],
            ['e', 'c', 'a', 'd'],
            ['e', 'c', 'd', 'a'],
            ['e', 'd', 'a', 'c'],
            ['e', 'd', 'c', 'a'],
        ];

        $cases = [];
        foreach ($orders as $offset => $order) {
            $arms = array_map(
                static fn (string $target): array => [
                    'target' => [$target],
                    'action' => 'update',
                    'assignments' => ['b' => static fn (): string => $target],
                ],
                $order,
            );
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $cases[] = [
                'upstream' => 'upsert5-order-' . ($offset + 1),
                'source' => 'upsert5.test',
                'order' => $order,
                'incoming' => $incoming,
                'selected' => $order[0],
                'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
                    'a',
                    'selected_arm' => 'b',
                    'conflict_c' => 'c',
                    'conflict_d' => 'd',
                    'conflict_e' => 'e',
                ]),
                'after' => $plan['after'],
                'matched' => array_map(
                    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
                    $plan['matched_arms'],
                ),
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,before:list<array<string,mixed>>,incoming:list<array<string,mixed>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,expected:list<array<string,mixed>>,returning:list<array<string,mixed>>,skipped:int,changes:int}>
     */
    public static function nullAndPartialConflictCases(): array
    {
        $rows = [
            ['a' => 1, 'b' => null, 'c' => 'one'],
            ['a' => 2, 'b' => null, 'c' => 'two'],
            ['a' => 3, 'b' => 30, 'c' => 'thirty'],
        ];
        $constraints = [['a'], ['b']];
        $arms = [
            ['target' => ['b'], 'action' => 'nothing'],
            ['target' => null, 'action' => 'update', 'assignments' => ['c' => static fn (array $row, array $incoming): string => (string) $incoming['c']]],
        ];

        $incomingSets = [
            'upsert4-1.1' => [['a' => 4, 'b' => null, 'c' => 'null-is-distinct']],
            'upsert4-1.2' => [['a' => 4, 'b' => 30, 'c' => 'skip-b']],
            'upsert4-1.3' => [['a' => 2, 'b' => 20, 'c' => 'update-a']],
            'upsert4-2.1' => [['a' => 5, 'b' => 50, 'c' => 'insert-new']],
        ];

        $cases = [];
        foreach ($incomingSets as $upstream => $incoming) {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, $incoming, $arms, $constraints);
            $cases[] = [
                'upstream' => $upstream,
                'source' => 'upsert4.test',
                'before' => $plan['before'],
                'incoming' => $incoming,
                'constraints' => $constraints,
                'arms' => $arms,
                'expected' => $plan['after'],
                'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c']),
                'skipped' => count($plan['skipped_rows']),
                'changes' => $plan['changes'],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,inserted:array<string,mixed>,deleted:list<array<string,mixed>>,after:list<array<string,mixed>>,returning:list<array<string,mixed>>,changes:int}>
     */
    public static function replaceOrderingCases(): array
    {
        $rows = [
            ['a' => 1, 'b' => 1, 'c' => 'one'],
            ['a' => 2, 'b' => 2, 'c' => 'two'],
        ];

        $cases = [];
        foreach ([
            'upsert4-3.10' => ['incoming' => ['a' => 3, 'b' => 1, 'c' => 'replace-a'], 'delete_on' => 'b'],
            'upsert4-3.20' => ['incoming' => ['a' => 1, 'b' => 3, 'c' => 'replace-b'], 'delete_on' => 'a'],
            'upsert4-3.30' => ['incoming' => ['a' => 3, 'b' => 3, 'c' => 'insert-clean'], 'delete_on' => null],
        ] as $upstream => $case) {
            $deleted = [];
            $after = [];
            foreach ($rows as $row) {
                $deleteOn = $case['delete_on'];
                if ($deleteOn !== null && $row[$deleteOn] === $case['incoming'][$deleteOn]) {
                    $deleted[] = $row;
                    continue;
                }
                $after[] = $row;
            }
            $after[] = $case['incoming'];
            $cases[] = [
                'upstream' => $upstream,
                'source' => 'upsert4.test',
                'inserted' => $case['incoming'],
                'deleted' => $deleted,
                'after' => $after,
                'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows([$case['incoming']], ['a', 'b', 'c']),
                'changes' => 1,
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,row:array<string,mixed>,projection:array<mixed>,expected:array<string,mixed>,column_names:list<string>}>
     */
    public static function returningProjectionCases(): array
    {
        $rows = [
            'returning1-1.1' => ['a' => 1, 'b' => 10, 'c' => 'bellum', 'rowid' => 1],
            'returning1-1.2' => ['a' => 2, 'b' => 'happy', 'c' => 'pax', 'rowid' => 2],
            'returning1-1.3' => ['a' => 3, 'b' => null, 'c' => 'pax', 'rowid' => 3],
            'returning1-10.1' => ['x' => 4, 'y' => 5, 'rowid' => 4],
            'returning1-15.1' => ['x' => null, 'quoted' => 'NULL', 'is_null' => 1],
        ];
        $projections = [
            'returning1-1.1' => ['a', 'b', 'c'],
            'returning1-1.2' => ['rowid', '*'],
            'returning1-1.3' => ['b_value' => 'b', 'c_value' => 'c'],
            'returning1-10.1' => ['sum' => static fn (array $row): int => (int) $row['x'] + (int) $row['y']],
            'returning1-15.1' => ['quote_x' => 'quoted', 'is_null' => 'is_null'],
        ];

        $cases = [];
        foreach ($rows as $upstream => $row) {
            $expected = SQLiteUpsertDoUpdateWherePlan::returningRows([$row], $projections[$upstream])[0];
            $cases[] = [
                'upstream' => $upstream,
                'source' => 'returning1.test',
                'row' => $row,
                'projection' => $projections[$upstream],
                'expected' => $expected,
                'column_names' => array_keys($expected),
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,parent_ids:list<int>,incoming:array<string,mixed>,error_before_returning:bool,returning:list<array<string,mixed>>}>
     */
    public static function returningConstraintOrderCases(): array
    {
        return [
            [
                'upstream' => 'returning1-12.1',
                'source' => 'returning1.test',
                'parent_ids' => [1, 2],
                'incoming' => ['id' => 10, 'parent_id' => 1],
                'error_before_returning' => false,
                'returning' => [['id' => 10]],
            ],
            [
                'upstream' => 'returning1-12.2',
                'source' => 'returning1.test',
                'parent_ids' => [1, 2],
                'incoming' => ['id' => 11, 'parent_id' => 123],
                'error_before_returning' => true,
                'returning' => [],
            ],
            [
                'upstream' => 'returning1-12.3',
                'source' => 'returning1.test',
                'parent_ids' => [],
                'incoming' => ['id' => 12, 'parent_id' => null],
                'error_before_returning' => false,
                'returning' => [['id' => 12]],
            ],
        ];
    }

    /**
     * @return list<array{upstream:string,source:string,storage:string,before:list<array<string,mixed>>,incoming:array<string,mixed>,arms:list<array<string,mixed>>,records:list<array{x:string,y:string}>,after:list<array<string,mixed>>,changes:int,skipped:int}>
     */
    public static function triggerLifecycleCases(): array
    {
        $rows = [['a' => 1, 'b' => 2, 'c' => 0]];
        $variants = [
            'upsert2-300' => 'rowid',
            'upsert2-400' => 'without-rowid',
        ];
        $caseSpecs = [
            'update' => [
                'suffix' => 'conflict update fires before insert and update triggers',
                'incoming' => ['a' => 1, 'b' => 2, 'c' => 0],
                'arms' => [[
                    'target' => ['a'],
                    'action' => 'update',
                    'assignments' => ['c' => static fn (array $current): int => (int) $current['c'] + 1],
                ]],
                'records' => [
                    ['x' => 'before-insert', 'y' => '1,2,0'],
                    ['x' => 'before-update', 'y' => '1,2,0/1,2,1'],
                    ['x' => 'after-update', 'y' => '1,2,0/1,2,1'],
                ],
                'after' => [['a' => 1, 'b' => 2, 'c' => 1]],
                'changes' => 1,
                'skipped' => 0,
            ],
            'nothing' => [
                'suffix' => 'do nothing fires before insert only',
                'incoming' => ['a' => 1, 'b' => 2, 'c' => 0],
                'arms' => [['target' => null, 'action' => 'nothing']],
                'records' => [
                    ['x' => 'before-insert', 'y' => '1,2,0'],
                ],
                'after' => $rows,
                'changes' => 0,
                'skipped' => 1,
            ],
            'where-false' => [
                'suffix' => 'failed update where fires before insert only',
                'incoming' => ['a' => 1, 'b' => 2, 'c' => 0],
                'arms' => [[
                    'target' => ['a'],
                    'action' => 'update',
                    'assignments' => ['c' => static fn (array $current): int => (int) $current['c'] + 1],
                    'where' => static fn (array $current): bool => (int) $current['c'] < 0,
                ]],
                'records' => [
                    ['x' => 'before-insert', 'y' => '1,2,0'],
                ],
                'after' => $rows,
                'changes' => 0,
                'skipped' => 1,
            ],
        ];

        $cases = [];
        foreach ($variants as $baseUpstream => $storage) {
            foreach ($caseSpecs as $kind => $spec) {
                $upstream = match ($kind) {
                    'update' => $baseUpstream,
                    'nothing' => $storage === 'rowid' ? 'upsert2-310' : 'upsert2-410',
                    'where-false' => $storage === 'rowid' ? 'upsert2-320/321' : 'upsert2-420/421',
                };
                $cases[] = [
                    'upstream' => $upstream,
                    'source' => 'upsert2.test',
                    'storage' => $storage,
                    'before' => $rows,
                    'incoming' => $spec['incoming'],
                    'arms' => $spec['arms'],
                    'records' => $spec['records'],
                    'after' => $spec['after'],
                    'changes' => $spec['changes'],
                    'skipped' => $spec['skipped'],
                ];
            }
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,schema:string,without_rowid:bool,columns:list<string>,before:list<array<string,mixed>>,incoming:array<string,mixed>,constraints:list<list<string>>,arms:list<array<string,mixed>>,expected:list<array<string,mixed>>,returning:list<array<string,mixed>>,matched:list<string>,changes:int,skipped:int,selected:string|null}>
     */
    public static function upsert5CatchAllPriorityCases(): array
    {
        $schemas = [
            1 => ['schema' => 'rowid integer primary key first', 'columns' => ['a', 'b', 'c', 'd', 'e'], 'constraints' => [['a'], ['c'], ['d'], ['e']], 'without_rowid' => false],
            2 => ['schema' => 'int primary key first', 'columns' => ['a', 'b', 'c', 'd', 'e'], 'constraints' => [['a'], ['c'], ['d'], ['e']], 'without_rowid' => false],
            3 => ['schema' => 'int primary key first without rowid', 'columns' => ['a', 'b', 'c', 'd', 'e'], 'constraints' => [['a'], ['c'], ['d'], ['e']], 'without_rowid' => true],
            4 => ['schema' => 'rowid integer primary key after unique columns', 'columns' => ['e', 'd', 'c', 'a', 'b'], 'constraints' => [['e'], ['d'], ['c'], ['a']], 'without_rowid' => false],
            5 => ['schema' => 'int primary key after unique columns', 'columns' => ['e', 'd', 'c', 'a', 'b'], 'constraints' => [['e'], ['d'], ['c'], ['a']], 'without_rowid' => false],
            6 => ['schema' => 'int primary key after unique columns without rowid', 'columns' => ['e', 'd', 'c', 'a', 'b'], 'constraints' => [['e'], ['d'], ['c'], ['a']], 'without_rowid' => true],
        ];

        $rows = [['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]];
        $cases = [];
        $specs = [
            100 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'order' => ['a', 'c', 'd', 'e'], 'expected_b' => 'a'],
            101 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'order' => ['a', 'c', 'd', 'e'], 'expected_b' => 'c'],
            102 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'order' => ['a', 'c', 'd', 'e'], 'expected_b' => 'd'],
            103 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['a', 'c', 'd', 'e'], 'expected_b' => 'e'],
            200 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'a', 'd', 'e'], 'expected_b' => 'a'],
            201 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'order' => ['c', 'a', 'd', 'e'], 'expected_b' => 'c'],
            202 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 5], 'order' => ['c', 'a', 'd', 'e'], 'expected_b' => 'c'],
            203 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'a', 'd', 'e'], 'expected_b' => 'a'],
            204 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'order' => ['c', 'a', 'd', 'e'], 'expected_b' => 'a'],
            210 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', 'a', 'e'], 'expected_b' => 'a'],
            211 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'order' => ['c', 'd', 'a', 'e'], 'expected_b' => 'd'],
            212 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', 'a', 'e'], 'expected_b' => 'a'],
            213 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', 'a', 'e'], 'expected_b' => 'e'],
            214 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', 'e', 'a'], 'expected_b' => 'e'],
            215 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', 'e', 'a'], 'expected_b' => 'e'],
            216 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', 'e', 'a'], 'expected_b' => 'a'],
            300 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', 'a1', 'a2', 'a3', 'a4', 'a5', 'e'], 'expected_b' => 'a1'],
            301 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', 'a1', 'a2', 'a3', 'a4', 'a5', 'e'], 'expected_b' => 'e'],
            400 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*x'], 'expected_b' => 'x'],
            401 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', '*x'], 'expected_b' => 'x'],
            402 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*x'], 'expected_b' => 'x'],
            403 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*x'], 'expected_b' => 'c'],
            404 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'order' => ['c', 'd', '*x'], 'expected_b' => 'c'],
            405 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'order' => ['c', 'd', '*x'], 'expected_b' => 'd'],
            410 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['*x'], 'expected_b' => 'x'],
            411 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['*x'], 'expected_b' => 'x'],
            412 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'order' => ['*x'], 'expected_b' => 'x'],
            413 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'order' => ['*x'], 'expected_b' => 'x'],
            420 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c:nothing', 'd:nothing', '*x'], 'expected_b' => 'x'],
            421 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c:nothing', 'd:nothing', '*x'], 'expected_b' => 'x'],
            422 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 95], 'order' => ['c:nothing', 'd:nothing', '*x'], 'expected_b' => 2],
            423 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'order' => ['c:nothing', 'd:nothing', '*x'], 'expected_b' => 2],
            500 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 2],
            501 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 5], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 2],
            502 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 2],
            503 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 94, 'e' => 95], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 'c'],
            504 => ['incoming' => ['a' => 91, 'b' => null, 'c' => 3, 'd' => 4, 'e' => 95], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 'c'],
            505 => ['incoming' => ['a' => 1, 'b' => null, 'c' => 93, 'd' => 4, 'e' => 5], 'order' => ['c', 'd', '*nothing'], 'expected_b' => 'd'],
        ];

        foreach ($schemas as $schemaNumber => $schema) {
            foreach ($specs as $caseNumber => $spec) {
                $arms = self::upsert5Arms($spec['order']);
                $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$spec['incoming']], $arms, $schema['constraints']);
                $expected = [['a' => 1, 'b' => $spec['expected_b'], 'c' => 3, 'd' => 4, 'e' => 5]];
                $cases[] = [
                    'upstream' => 'upsert5-1.' . $schemaNumber . '.' . $caseNumber,
                    'source' => 'upsert5.test',
                    'schema' => $schema['schema'],
                    'without_rowid' => $schema['without_rowid'],
                    'columns' => $schema['columns'],
                    'before' => $rows,
                    'incoming' => $spec['incoming'],
                    'constraints' => $schema['constraints'],
                    'arms' => $arms,
                    'expected' => $expected,
                    'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e']),
                    'matched' => array_map(
                        static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
                        $plan['matched_arms'],
                    ),
                    'changes' => $plan['changes'],
                    'skipped' => count($plan['skipped_rows']),
                    'selected' => $spec['expected_b'] === 2 ? null : (string) $spec['expected_b'],
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array{upstream:string,source:string,statement:string,error:string,resolved_before_execution:bool}
     */
    public static function unresolvedConflictTargetCase(): array
    {
        return [
            'upstream' => 'upsert5-2.1',
            'source' => 'upsert5.test',
            'statement' => 'INSERT INTO t2(a,b,c,e,d) VALUES(1,2,3,4,5) ON CONFLICT(c) DO UPDATE SET b=\'\' ON CONFLICT((SELECT t2 FROM nosuchtable)) DO NOTHING',
            'error' => 'no such table: nosuchtable',
            'resolved_before_execution' => true,
        ];
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,table:string,replace_row:array<string,int>,before:list<array<string,int>>,after:list<array<string,int>>,table_scan:list<array<string,int>>,indexes:array<string,list<array<string,int>>>,integrity:string,redundant_targets:list<string>,changed:int,deleted:int,inserted:int}>
     */
    public static function redundantConflictIntegrityCases(int $seedCount = 24): array
    {
        $cases = [];
        for ($seed = 1; $seed <= $seedCount; ++$seed) {
            $offset = $seed * 1000;
            $singleBefore = [
                ['aa' => $offset + 11, 'bb' => $offset + 22],
            ];
            $singleReplace = ['aa' => $offset + 11, 'bb' => $offset + 33];
            $singleAfter = [$singleReplace];
            $cases[] = [
                'upstream' => 'upsert5-3.0',
                'source' => 'upsert5.test',
                'seed' => $seed,
                'table' => 't1',
                'replace_row' => $singleReplace,
                'before' => $singleBefore,
                'after' => $singleAfter,
                'table_scan' => $singleAfter,
                'indexes' => [
                    't1bb' => self::orderedRows($singleAfter, ['aa']),
                ],
                'integrity' => 'ok',
                'redundant_targets' => ['bb', 'bb'],
                'changed' => 1,
                'deleted' => 1,
                'inserted' => 1,
            ];

            $tripleBefore = [
                ['aa' => $offset + 10, 'bb' => $offset + 21, 'cc' => $offset + 32],
                ['aa' => $offset + 11, 'bb' => $offset + 22, 'cc' => $offset + 33],
                ['aa' => $offset + 12, 'bb' => $offset + 23, 'cc' => $offset + 34],
            ];
            $tripleReplace = ['aa' => $offset + 11, 'bb' => $offset + 44, 'cc' => $offset + 55];
            $tripleAfter = [
                $tripleBefore[0],
                $tripleReplace,
                $tripleBefore[2],
            ];
            $cases[] = [
                'upstream' => 'upsert5-3.3/3.4/3.5/3.6',
                'source' => 'upsert5.test',
                'seed' => $seed,
                'table' => 't1',
                'replace_row' => $tripleReplace,
                'before' => $tripleBefore,
                'after' => $tripleAfter,
                'table_scan' => self::orderedRows($tripleAfter, ['aa']),
                'indexes' => [
                    't1bb' => self::orderedRows($tripleAfter, ['aa']),
                    't1cc' => self::orderedRows($tripleAfter, ['aa']),
                ],
                'integrity' => 'ok',
                'redundant_targets' => ['bb', 'cc', 'bb'],
                'changed' => 1,
                'deleted' => 1,
                'inserted' => 1,
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,before:list<array<string,int|string>>,incoming:list<array<string,int|string>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,after:list<array<string,int|string>>,returning:list<array<string,int|string>>,events:list<string>,changes:int,skipped:int,dependencies:list<string>}>
     */
    public static function upsert2WhereFalseReturningDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream UPSERT WHERE-false corpus case count must be positive');
        }

        $cases = [];
        for ($seed = 1; $seed <= $caseCount; ++$seed) {
            $base = $seed * 100;
            $before = [
                ['a' => $base + 1, 'b' => $base + 10, 'c' => 0, 'tag' => 'seed-a'],
                ['a' => $base + 2, 'b' => $base + 20, 'c' => 0, 'tag' => 'seed-b'],
            ];
            $incoming = [
                ['a' => $base + 1, 'b' => $base + 11, 'c' => 99, 'tag' => 'where-false'],
                ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
                ['a' => $base + 1, 'b' => $base + 40, 'c' => 0, 'tag' => 'where-true'],
            ];
            $constraints = [['a']];
            $arms = [[
                'target' => ['a'],
                'action' => 'update',
                'assignments' => [
                    'b' => static fn (array $current, array $row): int => (int) $row['b'],
                    'c' => static fn (array $current): int => (int) $current['c'] + 1,
                    'tag' => static fn (array $current, array $row): string => (string) $row['tag'],
                ],
                'where' => static fn (array $current, array $row): bool => (int) $row['c'] === 0,
            ]];
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($before, $incoming, $arms, $constraints);
            $after = [
                ['a' => $base + 1, 'b' => $base + 40, 'c' => 1, 'tag' => 'where-true'],
                ['a' => $base + 2, 'b' => $base + 20, 'c' => 0, 'tag' => 'seed-b'],
                ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
            ];

            $cases[] = [
                'upstream' => 'upsert2-320/321-dynamic-' . $seed,
                'source' => 'upsert2.test',
                'seed' => $seed,
                'before' => $before,
                'incoming' => $incoming,
                'constraints' => $constraints,
                'arms' => $arms,
                'after' => $after,
                'returning' => [
                    ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
                    ['a' => $base + 1, 'b' => $base + 40, 'c' => 1, 'tag' => 'where-true'],
                ],
                'events' => array_column($plan['yield_trace'], 'event'),
                'changes' => 2,
                'skipped' => 1,
                'dependencies' => [
                    'upsert2.test-320-321',
                    'returning1.test-4',
                    'sqlite-upsert-conflict-arm-yield-trace',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,before:list<array<string,int|string>>,incoming:list<array<string,int|string>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,after:list<array<string,int|string>>,returning:list<array<string,int|string>>,events:list<string>,matched:list<string>,changes:int,skipped:int,dependencies:list<string>}>
     */
    public static function upsert2RepeatedConflictReturningDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream UPSERT repeated-conflict corpus case count must be positive');
        }

        $cases = [];
        for ($seed = 1; $seed <= $caseCount; ++$seed) {
            $base = $seed * 1000;
            $before = [
                ['a' => $base + 1, 'b' => $base + 10, 'c' => 0, 'tag' => 'seed-a'],
                ['a' => $base + 2, 'b' => $base + 20, 'c' => 0, 'tag' => 'seed-b'],
            ];
            $incoming = [
                ['a' => $base + 1, 'b' => $base + 11, 'c' => 1, 'tag' => 'first-update'],
                ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
                ['a' => $base + 1, 'b' => $base + 12, 'c' => 2, 'tag' => 'second-update'],
                ['a' => $base + 2, 'b' => $base + 19, 'c' => 9, 'tag' => 'where-false'],
                ['a' => $base + 2, 'b' => $base + 25, 'c' => 3, 'tag' => 'third-update'],
            ];
            $constraints = [['a']];
            $arms = [[
                'target' => ['a'],
                'action' => 'update',
                'assignments' => [
                    'b' => static fn (array $current, array $row): int => (int) $row['b'],
                    'c' => static fn (array $current): int => (int) $current['c'] + 1,
                    'tag' => static fn (array $current, array $row): string => (string) $row['tag'],
                ],
                'where' => static fn (array $current, array $row): bool => (int) $row['b'] > (int) $current['b'],
            ]];
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($before, $incoming, $arms, $constraints);

            $cases[] = [
                'upstream' => 'upsert2-200/201-repeated-conflict-returning-dynamic-' . $seed,
                'source' => 'upsert2.test + returning1.test',
                'seed' => $seed,
                'before' => $before,
                'incoming' => $incoming,
                'constraints' => $constraints,
                'arms' => $arms,
                'after' => [
                    ['a' => $base + 1, 'b' => $base + 12, 'c' => 2, 'tag' => 'second-update'],
                    ['a' => $base + 2, 'b' => $base + 25, 'c' => 1, 'tag' => 'third-update'],
                    ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
                ],
                'returning' => [
                    ['a' => $base + 1, 'b' => $base + 11, 'c' => 1, 'tag' => 'first-update'],
                    ['a' => $base + 3, 'b' => $base + 30, 'c' => 0, 'tag' => 'inserted'],
                    ['a' => $base + 1, 'b' => $base + 12, 'c' => 2, 'tag' => 'second-update'],
                    ['a' => $base + 2, 'b' => $base + 25, 'c' => 1, 'tag' => 'third-update'],
                ],
                'events' => array_column($plan['yield_trace'], 'event'),
                'matched' => array_map(
                    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
                    $plan['matched_arms'],
                ),
                'changes' => 4,
                'skipped' => 1,
                'dependencies' => [
                    'upsert2.test-200-201',
                    'returning1.test-4.5',
                    'sqlite-upsert-repeated-conflict-current-row-image',
                    'sqlite-returning-changed-row-stream',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,schema:string,without_rowid:bool,before:list<array<string,int|string>>,incoming:list<array<string,int|string>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,after:list<array<string,int|string>>,returning:list<array<string,int|string>>,matched:list<string>,changes:int,skipped:int,dependencies:list<string>}>
     */
    public static function upsert1TargetFirstReturningDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream UPSERT target-first corpus case count must be positive');
        }

        $schemas = [
            ['upstream_base' => 700, 'schema' => 'rowid integer primary key with secondary unique indexes', 'without_rowid' => false, 'constraints' => [['a'], ['b'], ['e']]],
            ['upstream_base' => 730, 'schema' => 'rowid table with explicit unique indexes', 'without_rowid' => false, 'constraints' => [['a'], ['b'], ['e']]],
            ['upstream_base' => 760, 'schema' => 'without-rowid table with explicit unique indexes', 'without_rowid' => true, 'constraints' => [['a'], ['b'], ['e']]],
        ];
        $targets = [
            'e' => ['upstream_offset' => 0, 'target' => ['e'], 'selected' => 'e'],
            'a' => ['upstream_offset' => 10, 'target' => ['a'], 'selected' => 'a'],
            'b' => ['upstream_offset' => 20, 'target' => ['b'], 'selected' => 'b'],
        ];

        $cases = [];
        for ($seed = 1; count($cases) < $caseCount; ++$seed) {
            $base = $seed * 1000;
            $before = [[
                'a' => $base + 1,
                'b' => $base + 2,
                'c' => $base + 3,
                'd' => $base + 4,
                'e' => $base + 5,
                'setting_key' => 'seed-' . $seed,
            ]];
            $incoming = [[
                'a' => $base + 1,
                'b' => $base + 2,
                'c' => $base + 33,
                'd' => $base + 44,
                'e' => $base + 5,
                'setting_key' => 'incoming-' . $seed,
            ]];

            foreach ($schemas as $schema) {
                foreach ($targets as $target) {
                    $arms = [[
                        'target' => $target['target'],
                        'action' => 'update',
                        'assignments' => [
                            'c' => static fn (array $current, array $row): int => (int) $row['c'],
                            'setting_key' => static fn (array $current, array $row): string => (string) $row['setting_key'],
                        ],
                    ]];
                    $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($before, $incoming, $arms, $schema['constraints']);
                    $after = [[
                        'a' => $base + 1,
                        'b' => $base + 2,
                        'c' => $base + 33,
                        'd' => $base + 4,
                        'e' => $base + 5,
                        'setting_key' => 'incoming-' . $seed,
                    ]];

                    $cases[] = [
                        'upstream' => 'upsert1-' . ((int) $schema['upstream_base'] + (int) $target['upstream_offset']),
                        'source' => 'upsert1.test',
                        'seed' => $seed,
                        'schema' => $schema['schema'],
                        'without_rowid' => $schema['without_rowid'],
                        'before' => $before,
                        'incoming' => $incoming,
                        'constraints' => $schema['constraints'],
                        'arms' => $arms,
                        'after' => $after,
                        'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], ['a', 'b', 'c', 'd', 'e', 'setting_key']),
                        'matched' => array_map(
                            static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
                            $plan['matched_arms'],
                        ),
                        'changes' => $plan['changes'],
                        'skipped' => count($plan['skipped_rows']),
                        'dependencies' => [
                            'upsert1.test-700-through-780',
                            'returning1.test-4',
                            'sqlite-upsert-target-constraint-tested-first',
                        ],
                    ];

                    if (count($cases) >= $caseCount) {
                        break 3;
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,variant:string,before:list<array<string,int|string>>,incoming:list<array<string,int|string>>,conflict_target:list<string>,partial_index:string,after:list<array<string,int|string>>,returning:list<array<string,mixed>>,decisions:list<string>,changes:int,skipped:int,dependencies:list<string>}>
     */
    public static function upsert4PartialIndexReturningDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream UPSERT partial-index corpus case count must be positive');
        }

        $variants = [
            [
                'upstream' => 'upsert4-4.1.2',
                'variant' => 'x target with y greater than zero matches partial unique index',
                'conflict_target' => ['x'],
                'partial_index' => 'y>0',
                'candidate' => static fn (int $base): array => ['id' => $base + 5, 'x' => 'one-' . $base, 'y' => 10, 'payload' => 'candidate-x-match-' . $base],
                'partial' => static fn (array $row): bool => (int) $row['y'] > 0,
            ],
            [
                'upstream' => 'upsert4-4.1.3',
                'variant' => 'x target without matching partial predicate inserts a distinct row',
                'conflict_target' => ['x'],
                'partial_index' => 'y>0',
                'candidate' => static fn (int $base): array => ['id' => $base + 5, 'x' => 'one-' . $base, 'y' => -10, 'payload' => 'candidate-x-miss-' . $base],
                'partial' => static fn (array $row): bool => (int) $row['y'] > 0,
            ],
            [
                'upstream' => 'upsert4-4.1.5',
                'variant' => 'y target with nocase xyz predicate matches partial unique index',
                'conflict_target' => ['y'],
                'partial_index' => "x='xyz' COLLATE nocase",
                'candidate' => static fn (int $base): array => ['id' => $base + 5, 'x' => 'xYz', 'y' => 3, 'payload' => 'candidate-y-nocase-' . $base],
                'partial' => static fn (array $row): bool => strtolower((string) $row['x']) === 'xyz',
            ],
            [
                'upstream' => 'upsert4-4.2.3',
                'variant' => 'y target binary predicate misses nocase-only candidate',
                'conflict_target' => ['y'],
                'partial_index' => "x='xyz' COLLATE binary",
                'candidate' => static fn (int $base): array => ['id' => $base + 5, 'x' => 'xYz', 'y' => 3, 'payload' => 'candidate-y-binary-miss-' . $base],
                'partial' => static fn (array $row): bool => (string) $row['x'] === 'xyz',
            ],
        ];

        $cases = [];
        for ($seed = 1; count($cases) < $caseCount; ++$seed) {
            $base = $seed * 100;
            $before = [
                ['id' => $base + 1, 'x' => 'one-' . $base, 'y' => 1, 'payload' => 'seed-one-' . $base],
                ['id' => $base + 2, 'x' => 'two-' . $base, 'y' => 2, 'payload' => 'seed-two-' . $base],
                ['id' => $base + 3, 'x' => 'xyz', 'y' => 3, 'payload' => 'seed-xyz-lower-' . $base],
                ['id' => $base + 4, 'x' => 'XYZ', 'y' => 4, 'payload' => 'seed-xyz-upper-' . $base],
            ];

            foreach ($variants as $variant) {
                $incoming = [$variant['candidate']($base)];
                $plan = SQLiteUpsertReturningDynamicPlan::execute(
                    $before,
                    $incoming,
                    ['id', 'x', 'y', 'payload'],
                    $variant['conflict_target'],
                    [],
                    [],
                    ['id', 'x', 'y', 'payload'],
                    $variant['partial'],
                    true,
                );

                $cases[] = [
                    'upstream' => $variant['upstream'],
                    'source' => 'upsert4.test',
                    'seed' => $seed,
                    'variant' => $variant['variant'],
                    'before' => $before,
                    'incoming' => $incoming,
                    'conflict_target' => $variant['conflict_target'],
                    'partial_index' => $variant['partial_index'],
                    'after' => $plan['after'],
                    'returning' => $plan['returning_rows'],
                    'decisions' => array_column($plan['decisions'], 'action'),
                    'changes' => $plan['changes'],
                    'skipped' => count($plan['skipped_rows']),
                    'dependencies' => [
                        'upsert4.test-4.1.2-through-4.2.3',
                        'sqlite-upsert-partial-unique-conflict-target',
                        'sqlite-returning-changed-row-stream',
                    ],
                ];

                if (count($cases) >= $caseCount) {
                    break 2;
                }
            }
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,incoming:list<int>,after:list<array{x:int,cnt:int}>,returning:list<array{x:int,cnt:int,event:string,ordinal:int}>,events:list<string>,changes:int,dependencies:list<string>}>
     */
    public static function upsert4TriggerHistogramReturningDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream UPSERT trigger histogram corpus case count must be positive');
        }

        $cases = [];
        for ($seed = 1; $seed <= $caseCount; ++$seed) {
            $incoming = [];
            $distinct = 3 + ($seed % 5);
            $base = ($seed * 17) % 997;
            $length = 8 + ($seed % 9);
            for ($i = 0; $i < $length; ++$i) {
                $slot = (($i * (($seed % 3) + 1)) + intdiv($i, 3) + $seed) % $distinct;
                $incoming[] = $base + $slot;
            }
            if (!in_array($base, $incoming, true)) {
                $incoming[] = $base;
            }

            $histogram = [];
            $returning = [];
            $events = [];
            foreach ($incoming as $offset => $value) {
                $event = array_key_exists($value, $histogram) ? 'update' : 'insert';
                $histogram[$value] = ($histogram[$value] ?? 0) + 1;
                $events[] = $event;
                $returning[] = [
                    'x' => $value,
                    'cnt' => $histogram[$value],
                    'event' => $event,
                    'ordinal' => $offset + 1,
                ];
            }

            ksort($histogram);
            $after = [];
            foreach ($histogram as $value => $count) {
                $after[] = ['x' => (int) $value, 'cnt' => $count];
            }

            $cases[] = [
                'upstream' => 'upsert4.test-9.1-trigger-histogram-dynamic-' . sprintf('%04d', $seed),
                'source' => 'upsert4.test',
                'seed' => $seed,
                'incoming' => $incoming,
                'after' => $after,
                'returning' => $returning,
                'events' => $events,
                'changes' => count($incoming),
                'dependencies' => [
                    'upsert4.test-9.0',
                    'upsert4.test-9.1',
                    'sqlite-trigger-body-upsert',
                    'sqlite-upsert-returning-changed-row-stream',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,seed:int,storage:string,before:list<array<string,int|string>>,incoming:list<array<string,int|string>>,constraints:list<list<string>>,arms:list<array<string,mixed>>,after:list<array<string,int|string>>,returning:list<array{fooid:int}>,events:list<string>,trigger_checks:list<array{old_y:string,new_y:string,passes:bool}>,changes:int,dependencies:list<string>}>
     */
    public static function returning1RepeatedFoovalDynamicCases(int $caseCount = 1000): array
    {
        if ($caseCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream RETURNING repeated UPSERT corpus case count must be positive');
        }

        $cases = [];
        for ($seed = 1; $seed <= $caseCount; ++$seed) {
            $base = $seed * 1000;
            $firstValue = $base + 17;
            $secondValue = $base + 4711;
            $thirdValue = $base + 33;
            $storage = $seed % 2 === 0 ? 'temp' : 'main';
            $largeA = str_repeat('a', 96 + ($seed % 11));
            $largeB = str_repeat('b', 104 + ($seed % 13));

            $before = [
                ['fooid' => $base + 1, 'fooval' => $firstValue, 'refcnt' => 1, 'payload' => $largeA],
                ['fooid' => $base + 2, 'fooval' => $secondValue, 'refcnt' => 1, 'payload' => $largeB],
            ];
            $incoming = [
                ['fooid' => $base + 3, 'fooval' => $firstValue, 'refcnt' => 1, 'payload' => $largeA],
                ['fooid' => $base + 4, 'fooval' => $secondValue, 'refcnt' => 1, 'payload' => $largeB],
                ['fooid' => $base + 5, 'fooval' => $firstValue, 'refcnt' => 1, 'payload' => $largeA],
                ['fooid' => $base + 6, 'fooval' => $thirdValue, 'refcnt' => 1, 'payload' => 'insert-' . $seed],
                ['fooid' => $base + 7, 'fooval' => $secondValue, 'refcnt' => 1, 'payload' => $largeB],
            ];
            $arms = [[
                'target' => null,
                'action' => 'update',
                'assignments' => [
                    'refcnt' => static fn (array $current): int => (int) $current['refcnt'] + 1,
                    'payload' => static fn (array $current, array $row): string => (string) $row['payload'],
                ],
            ]];
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace($before, $incoming, $arms, [['fooval']]);
            $triggerChecks = [];
            foreach ($plan['yield_trace'] as $event) {
                if (($event['event'] ?? '') !== 'update-returning') {
                    continue;
                }
                $oldY = (string) (($event['old'] ?? [])['payload'] ?? '');
                $newY = (string) (($event['row'] ?? [])['payload'] ?? '');
                $triggerChecks[] = [
                    'old_y' => $oldY,
                    'new_y' => $newY,
                    'passes' => $oldY === $newY,
                ];
            }

            $cases[] = [
                'upstream' => 'returning1-17.' . ($storage === 'main' ? '1' : '2') . '/upsert1-1300-dynamic-' . sprintf('%04d', $seed),
                'source' => 'returning1.test + upsert1.test',
                'seed' => $seed,
                'storage' => $storage,
                'before' => $before,
                'incoming' => $incoming,
                'constraints' => [['fooval']],
                'arms' => $arms,
                'after' => [
                    ['fooid' => $base + 1, 'fooval' => $firstValue, 'refcnt' => 3, 'payload' => $largeA],
                    ['fooid' => $base + 2, 'fooval' => $secondValue, 'refcnt' => 3, 'payload' => $largeB],
                    ['fooid' => $base + 6, 'fooval' => $thirdValue, 'refcnt' => 1, 'payload' => 'insert-' . $seed],
                ],
                'returning' => [
                    ['fooid' => $base + 1],
                    ['fooid' => $base + 2],
                    ['fooid' => $base + 1],
                    ['fooid' => $base + 6],
                    ['fooid' => $base + 2],
                ],
                'events' => array_column($plan['yield_trace'], 'event'),
                'trigger_checks' => $triggerChecks,
                'changes' => 5,
                'dependencies' => [
                    'returning1.test-17.1-through-17.2',
                    'upsert1.test-1300',
                    'sqlite-returning-repeated-upsert-row-stream',
                    'sqlite-upsert-trigger-current-row-image',
                ],
            ];
        }

        return $cases;
    }

    /**
     * @param list<string> $order
     * @return list<array<string,mixed>>
     */
    private static function upsert5Arms(array $order): array
    {
        $arms = [];
        foreach ($order as $term) {
            if ($term === '*x') {
                $arms[] = ['target' => null, 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'x']];
                continue;
            }
            if ($term === '*nothing') {
                $arms[] = ['target' => null, 'action' => 'nothing'];
                continue;
            }
            if (str_ends_with($term, ':nothing')) {
                $target = substr($term, 0, -8);
                $arms[] = ['target' => [$target], 'action' => 'nothing'];
                continue;
            }
            $target = $term[0];
            $arms[] = ['target' => [$target], 'action' => 'update', 'assignments' => ['b' => static fn (): string => $term]];
        }

        return $arms;
    }

    /**
     * @param list<array<string,int>> $rows
     * @param list<string> $columns
     * @return list<array<string,int>>
     */
    private static function orderedRows(array $rows, array $columns): array
    {
        usort($rows, static function (array $left, array $right) use ($columns): int {
            foreach ($columns as $column) {
                $comparison = $left[$column] <=> $right[$column];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        });

        return array_values($rows);
    }
}
