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
}
