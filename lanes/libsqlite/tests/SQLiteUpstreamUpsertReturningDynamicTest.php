<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['id', 'key_name', 'value_text', 'load_policy'];
$defaults = ['load_policy' => 'lazy'];

$upsertDoNothing = SQLiteUpsertReturningDynamicPlan::execute(
    [],
    [
        ['id' => 1, 'key_name' => 'alpha', 'value_text' => 'one'],
        ['id' => 1, 'key_name' => 'alpha-rowid', 'value_text' => 'rowid-conflict'],
        ['id' => 99, 'key_name' => 'alpha', 'value_text' => 'unique-conflict'],
    ],
    $columns,
    ['id'],
    $defaults,
    [],
    '*',
    null,
    true,
    'id',
    [['key_name']],
);

$upsertTargetedKey = SQLiteUpsertReturningDynamicPlan::execute(
    [
        ['id' => 3, 'key_name' => 'beta', 'value_text' => 'kept', 'load_policy' => 'eager'],
    ],
    [
        ['id' => 9, 'key_name' => 'beta', 'value_text' => 'skipped'],
        ['id' => 10, 'key_name' => 'gamma', 'value_text' => 'added'],
    ],
    $columns,
    ['key_name'],
    $defaults,
    [],
    ['id', 'key_name', 'load_policy'],
    null,
    true,
);

$upsertReturning = SQLiteUpsertReturningDynamicPlan::execute(
    [
        ['id' => 1, 'key_name' => 'alpha', 'value_text' => 'old-alpha', 'load_policy' => 'lazy'],
        ['id' => 2, 'key_name' => 'beta', 'value_text' => 'old-beta', 'load_policy' => 'eager'],
        ['id' => 3, 'key_name' => 'archive', 'value_text' => 'cold', 'load_policy' => 'archived'],
    ],
    [
        ['id' => 8, 'key_name' => 'alpha', 'value_text' => 'new-alpha', 'load_policy' => 'eager'],
        ['id' => 4, 'key_name' => 'delta', 'value_text' => 'new-delta'],
        ['id' => 9, 'key_name' => 'beta', 'value_text' => 'new-beta', 'load_policy' => 'lazy'],
        ['id' => 5, 'key_name' => null, 'value_text' => 'anonymous'],
        ['id' => 10, 'key_name' => 'archive', 'value_text' => 'warm', 'load_policy' => 'active'],
    ],
    $columns,
    ['key_name'],
    $defaults,
    ['value_text' => 'excluded.value_text', 'load_policy' => 'excluded.load_policy'],
    '*',
);

$partialIndex = SQLiteUpsertReturningDynamicPlan::execute(
    [
        ['id' => 1, 'key_name' => 'draft', 'value_text' => 'old-draft', 'load_policy' => 'draft'],
        ['id' => 2, 'key_name' => 'live', 'value_text' => 'old-live', 'load_policy' => 'active'],
    ],
    [
        ['key_name' => 'draft', 'value_text' => 'new-draft', 'load_policy' => 'draft'],
        ['key_name' => 'live', 'value_text' => 'new-live', 'load_policy' => 'active'],
        ['key_name' => 'live', 'value_text' => 'inactive-live', 'load_policy' => 'archived'],
    ],
    $columns,
    ['key_name'],
    $defaults,
    ['value_text' => 'excluded.value_text'],
    ['id', 'key_name', 'value_text', 'load_policy'],
    static fn (array $row): bool => ($row['load_policy'] ?? null) === 'active',
);

$cases = [
    'upsert1-100 do nothing inserts first row' => [static fn (): mixed => $upsertDoNothing['after'], [['id' => 1, 'key_name' => 'alpha', 'value_text' => 'one', 'load_policy' => 'lazy']]],
    'upsert1-100 do nothing reports one change' => [static fn (): mixed => $upsertDoNothing['changes'], 1],
    'upsert1-100 rowid conflict skipped' => [static fn (): mixed => $upsertDoNothing['skipped_rows'][0]['value_text'], 'rowid-conflict'],
    'upsert1-100 second conflict skipped against alternate unique key' => [static fn (): mixed => $upsertDoNothing['decisions'][2]['action'], 'skip'],
    'upsert1-100 returning only inserted row' => [static fn (): mixed => array_column($upsertDoNothing['returning_rows'], 'key_name'), ['alpha']],
    'upsert1-101 targeted unique key keeps original' => [static fn (): mixed => $upsertTargetedKey['after'][0]['value_text'], 'kept'],
    'upsert1-101 targeted key skip action' => [static fn (): mixed => $upsertTargetedKey['decisions'][0]['action'], 'skip'],
    'upsert1-101 nonconflicting row appends' => [static fn (): mixed => $upsertTargetedKey['after'][1]['key_name'], 'gamma'],
    'upsert1-101 projected returning columns' => [static fn (): mixed => array_keys($upsertTargetedKey['returning_rows'][0]), ['id', 'key_name', 'load_policy', '_upsert_action', '_statement_sequence']],
    'upsert1-101 default load policy on insert' => [static fn (): mixed => $upsertTargetedKey['returning_rows'][0]['load_policy'], 'lazy'],
    'returning1-4.2 upsert update returns changed row' => [static fn (): mixed => $upsertReturning['returning_rows'][0]['value_text'], 'new-alpha'],
    'returning1-4.2 update action annotated' => [static fn (): mixed => $upsertReturning['returning_rows'][0]['_upsert_action'], 'update'],
    'returning1-4.2 old image retained for update' => [static fn (): mixed => $upsertReturning['returning_rows'][0]['_old']['value_text'], 'old-alpha'],
    'returning1-4.5 statement change order' => [static fn (): mixed => array_column($upsertReturning['returning_rows'], 'key_name'), ['alpha', 'delta', 'beta', null, 'archive']],
    'returning1-4.5 mixed actions' => [static fn (): mixed => array_column($upsertReturning['returning_rows'], '_upsert_action'), ['update', 'insert', 'update', 'insert', 'update']],
    'returning1-4.5 statement sequence preserved' => [static fn (): mixed => array_column($upsertReturning['returning_rows'], '_statement_sequence'), [1, 2, 3, 4, 5]],
    'returning1-4.5 inserted row gets next rowid' => [static fn (): mixed => $upsertReturning['inserted_rows'][0]['id'], 4],
    'returning1-4.5 null conflict key does not conflict' => [static fn (): mixed => $upsertReturning['inserted_rows'][1]['key_name'], null],
    'returning1-4.5 null conflict key gets following rowid' => [static fn (): mixed => $upsertReturning['inserted_rows'][1]['id'], 5],
    'returning1-4.5 update count' => [static fn (): mixed => count($upsertReturning['updated_rows']), 3],
    'returning1-4.5 insert count' => [static fn (): mixed => count($upsertReturning['inserted_rows']), 2],
    'returning1-4.5 change count' => [static fn (): mixed => $upsertReturning['changes'], 5],
    'returning1-4.5 after alpha updated' => [static fn (): mixed => $upsertReturning['after'][0]['load_policy'], 'eager'],
    'returning1-4.5 after beta updated' => [static fn (): mixed => $upsertReturning['after'][1]['value_text'], 'new-beta'],
    'returning1-4.5 after archive updated' => [static fn (): mixed => $upsertReturning['after'][2]['load_policy'], 'active'],
    'returning1-4.5 appended delta default' => [static fn (): mixed => $upsertReturning['after'][3]['load_policy'], 'lazy'],
    'returning1-4.5 conflict key records candidate' => [static fn (): mixed => $upsertReturning['decisions'][0]['candidate_key'], ['key_name' => 'alpha']],
    'returning1-4.5 conflict key records old' => [static fn (): mixed => $upsertReturning['decisions'][0]['conflict_key'], ['key_name' => 'alpha']],
    'returning1-4.5 insert has no conflict key' => [static fn (): mixed => $upsertReturning['decisions'][1]['conflict_key'], null],
    'upsert1-320 partial index ignores nonmatching existing draft' => [static fn (): mixed => $partialIndex['decisions'][0]['action'], 'insert'],
    'upsert1-320 partial index updates matching active row' => [static fn (): mixed => $partialIndex['decisions'][1]['action'], 'update'],
    'upsert1-320 partial index ignores nonmatching candidate' => [static fn (): mixed => $partialIndex['decisions'][2]['action'], 'insert'],
    'upsert1-320 partial index returning order' => [static fn (): mixed => array_column($partialIndex['returning_rows'], 'value_text'), ['new-draft', 'new-live', 'inactive-live']],
    'upsert1-320 partial index keeps active row position' => [static fn (): mixed => $partialIndex['after'][1]['value_text'], 'new-live'],
    'upsert1-320 partial index appends inactive duplicate' => [static fn (): mixed => $partialIndex['after'][3]['load_policy'], 'archived'],
    'invalid conflict target rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([], [], $columns, ['missing']), InvalidArgumentException::class],
    'empty conflict target rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([], [], $columns, []), InvalidArgumentException::class],
    'update without assignments rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([['id' => 1, 'key_name' => 'a']], [['id' => 1, 'key_name' => 'b']], $columns, ['id']), InvalidArgumentException::class],
    'invalid returning column rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([], [['key_name' => 'a']], $columns, ['key_name'], $defaults, [], ['bad-name']), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([], [['key_name' => 'a']], $columns, ['key_name'], $defaults, [], ['missing']), InvalidArgumentException::class],
    'bad assignment column rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([['id' => 1, 'key_name' => 'a']], [['id' => 1, 'key_name' => 'b']], $columns, ['id'], $defaults, ['bad-name' => 'excluded.value_text']), InvalidArgumentException::class],
    'missing excluded column rejected' => [static fn (): mixed => SQLiteUpsertReturningDynamicPlan::execute([['id' => 1, 'key_name' => 'a']], [['id' => 1, 'key_name' => 'b']], $columns, ['id'], $defaults, ['value_text' => 'excluded.missing']), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream corpus upsert returning dynamic ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
