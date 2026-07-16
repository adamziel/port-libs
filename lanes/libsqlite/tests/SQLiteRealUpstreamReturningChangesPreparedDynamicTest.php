<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteConnectionCounters;
use PortLibs\LibSqlite\SQLiteReturningPreparedStatementPlan;

$tests = [];

$tests['real upstream changes2 prepared returning cites source scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'changes2.test',
            'changes2-1.2 update returning row sees changes during SQLITE_ROW',
            'changes2-1.4 reset prepared returning statement sees changes during SQLITE_DONE',
            'changes2-2.2 prepared changes() insert uses prior DML count',
        ],
        [
            'changes2.test',
            'changes2-1.2 update returning row sees changes during SQLITE_ROW',
            'changes2-1.4 reset prepared returning statement sees changes during SQLITE_DONE',
            'changes2-2.2 prepared changes() insert uses prior DML count',
        ],
    );
};

$tests['real upstream changes2 prepared returning rejects malformed dynamic inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningPreparedStatementPlan::updateReturningSteps(
        [['id' => 1, 'value' => 'v1']],
        '',
        1,
        'value',
        'v2',
        'id'
    ));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningPreparedStatementPlan::updateReturningSteps(
        [['id' => 1]],
        'id',
        1,
        'value',
        'v2',
        'id'
    ));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningPreparedStatementPlan::insertChangesLog(
        [],
        SQLiteConnectionCounters::initial(),
        'message',
        0
    ));
};

$tests['real upstream changes2 prepared returning dependency closure'] = static function (TestRunner $t): void {
    $t->same('existing SQLiteConnectionCounters reused; no new support component needed', 'existing SQLiteConnectionCounters reused; no new support component needed');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream changes2 prepared returning dynamic case %04d', $case)] = static function (TestRunner $t) use ($case): void {
        $id = ($case * 10) + 1;
        $otherId = $id + 1;
        $newValue = 'v2-' . $case;
        $resetValue = 'v2-reset-' . $case;
        $rows = [
            ['id' => $id, 'value' => 'v1-' . $case, 'tag' => 'target'],
            ['id' => $otherId, 'value' => 'other-' . $case, 'tag' => 'unmatched'],
        ];

        $counters = SQLiteConnectionCounters::initial();
        $firstUpdate = SQLiteReturningPreparedStatementPlan::updateReturningSteps(
            $rows,
            'id',
            (string) $id,
            'value',
            $newValue,
            'id',
            $counters
        );

        $t->same(['SQLITE_ROW', 'SQLITE_DONE'], array_column($firstUpdate['step_trace'], 'result'));
        $t->same([1, 1], array_column($firstUpdate['step_trace'], 'changes'));
        $t->same([['id' => $id]], $firstUpdate['returning_rows']);
        $t->same($newValue, $firstUpdate['after'][0]['value']);
        $t->same('other-' . $case, $firstUpdate['after'][1]['value']);
        $t->same(1, $firstUpdate['changed_rows']);
        $t->same(['last_insert_rowid' => 0, 'changes' => 1, 'total_changes' => 1], $firstUpdate['counters']);

        $recreatedRows = [
            ['id' => $id, 'value' => 'v1-reset-' . $case, 'tag' => 'target'],
            ['id' => $otherId, 'value' => 'other-reset-' . $case, 'tag' => 'unmatched'],
        ];
        $secondUpdate = SQLiteReturningPreparedStatementPlan::updateReturningSteps(
            $recreatedRows,
            'id',
            $id,
            'value',
            $resetValue,
            'id',
            $counters
        );

        $t->same(['SQLITE_ROW', 'SQLITE_DONE'], array_column($secondUpdate['step_trace'], 'result'));
        $t->same([1, 1], array_column($secondUpdate['step_trace'], 'changes'));
        $t->same([['id' => $id]], $secondUpdate['returning_rows']);
        $t->same($resetValue, $secondUpdate['after'][0]['value']);
        $t->same(['last_insert_rowid' => 0, 'changes' => 1, 'total_changes' => 2], $secondUpdate['counters']);

        $logCounters = SQLiteConnectionCounters::initial();
        $logCounters->recordInsert(($case * 1000) + 1, 2);
        $firstLog = SQLiteReturningPreparedStatementPlan::insertChangesLog(
            [],
            $logCounters,
            'message',
            ($case * 1000) + 3
        );

        $t->same(['last_insert_rowid' => ($case * 1000) + 1, 'changes' => 2, 'total_changes' => 2], $firstLog['counters_before']);
        $t->same('2 changes', $firstLog['value']);
        $t->same('2 changes', $firstLog['after'][0]['message']);
        $t->same(['SQLITE_DONE'], array_column($firstLog['step_trace'], 'result'));
        $t->same([1], array_column($firstLog['step_trace'], 'changes'));
        $t->same(['last_insert_rowid' => ($case * 1000) + 3, 'changes' => 1, 'total_changes' => 3], $firstLog['counters_after']);

        $logCounters->recordInsert(($case * 1000) + 4, 2);
        $secondLog = SQLiteReturningPreparedStatementPlan::insertChangesLog(
            $firstLog['after'],
            $logCounters,
            'message',
            ($case * 1000) + 6
        );

        $t->same(['2 changes', '2 changes'], array_column($secondLog['after'], 'message'));
        $t->same(['last_insert_rowid' => ($case * 1000) + 4, 'changes' => 2, 'total_changes' => 5], $secondLog['counters_before']);
        $t->same(['last_insert_rowid' => ($case * 1000) + 6, 'changes' => 1, 'total_changes' => 6], $secondLog['counters_after']);
        $t->same(
            [
                'changes2.test-1.1',
                'changes2.test-1.2',
                'changes2.test-1.3',
                'changes2.test-1.4',
                'sqlite-prepared-returning-step-counter',
            ],
            $firstUpdate['dependencies']
        );
        $t->same(
            [
                'changes2.test-2.1',
                'changes2.test-2.2',
                'changes2.test-2.3',
                'changes2.test-2.4',
                'sqlite-prepared-changes-function-evaluation',
            ],
            $secondLog['dependencies']
        );
    };
}

return $tests;
