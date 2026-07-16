<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger2 statement order cites before after trigger loop' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'These tests ensure that BEFORE and AFTER triggers are fired at the correct'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER before_update_row BEFORE UPDATE ON tbl FOR EACH ROW'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER after_update_row AFTER UPDATE ON tbl FOR EACH ROW'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER conditional_update_row AFTER UPDATE ON tbl FOR EACH ROW'));
    },
    'real upstream trigger2 statement order cites delete and insert trigger loops' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER delete_before_row BEFORE DELETE ON tbl FOR EACH ROW'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER delete_after_row AFTER DELETE ON tbl FOR EACH ROW'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER insert_before_row BEFORE INSERT ON tbl FOR EACH ROW'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER insert_after_row AFTER INSERT ON tbl FOR EACH ROW'));
    },
    'real upstream trigger9 statement order cites before delete and update rollback blocks' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger9-1.2.1'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER trig1 BEFORE DELETE ON t1 BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER trig1 BEFORE UPDATE ON t1 BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger9-1.7.3 { execsql { ROLLBACK } } {}'));
    },
    'real upstream trigger9 statement order cites instead of view trigger blocks' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER trig1 INSTEAD OF UPDATE ON v1 BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr1 INSTEAD OF DELETE ON v1 BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr2 INSTEAD OF UPDATE ON v1 BEGIN'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER tr3 INSTEAD OF INSERT ON v1 BEGIN'));
    },
];

$sum = static function (array $rows): array {
    $a = 0;
    $b = 0;
    foreach ($rows as $row) {
        $a += $row['a'];
        $b += $row['b'];
    }

    return ['a' => $a, 'b' => $b];
};

for ($i = 1; $i <= 120; ++$i) {
    $initialRows = [
        ['a' => $i, 'b' => $i + 1],
        ['a' => $i + 2, 'b' => $i + 3],
        ['a' => $i + 4, 'b' => $i + 5],
    ];
    $insertRows = [
        ['a' => $i * 2, 'b' => ($i * 2) + 1],
        ['a' => $i * 3, 'b' => ($i * 3) + 1],
    ];
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder($initialRows, $insertRows);
    $updatedRows = array_map(static fn (array $row): array => ['a' => $row['a'] * 10, 'b' => $row['b'] * 10], $initialRows);
    $initialSum = $sum($initialRows);
    $firstUpdated = [$updatedRows[0], $initialRows[1], $initialRows[2]];
    $firstUpdatedSum = $sum($firstUpdated);
    $allUpdatedSum = $sum($updatedRows);
    $insertOneSum = $sum([$insertRows[0]]);
    $case = 'trigger2 trigger9 statement-order dynamic ' . $i;

    $tests[$case . ' source names trigger2 row order block'] = static function (TestRunner $t) use ($plan): void {
        $t->same('trigger2.test trigger2-1.1..1.3', $plan()['source']);
    };
    $tests[$case . ' dependency records before trigger prestatement image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array('sqlite-trigger2-before-trigger-sees-prestatement-rowset', $plan()['dependencies'], true));
    };
    $tests[$case . ' dependency records after trigger current row image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array('sqlite-trigger2-after-trigger-sees-current-row-change', $plan()['dependencies'], true));
    };
    $tests[$case . ' dependency records when clause old row image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array('sqlite-trigger2-when-clause-uses-old-row-image', $plan()['dependencies'], true));
    };
    $tests[$case . ' update log has before and after entries per row'] = static function (TestRunner $t) use ($plan): void {
        $t->same(6, $plan()['update_log_count']);
    };
    $tests[$case . ' delete log has before and after entries per row'] = static function (TestRunner $t) use ($plan): void {
        $t->same(6, $plan()['delete_log_count']);
    };
    $tests[$case . ' insert log has before and after entries per row'] = static function (TestRunner $t) use ($plan): void {
        $t->same(4, $plan()['insert_log_count']);
    };
    $tests[$case . ' conditional trigger fires only for first old row'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['conditional_update_log_count']);
    };
    $tests[$case . ' updated rows are stable tenfold values'] = static function (TestRunner $t) use ($plan, $updatedRows): void {
        $t->same($updatedRows, $plan()['updated_rows']);
    };
    $tests[$case . ' before update sees prestatement sum'] = static function (TestRunner $t) use ($plan, $initialRows, $initialSum): void {
        $first = $plan()['update_log'][0];
        $t->same([$initialRows[0]['a'], $initialRows[0]['b'], $initialSum['a'], $initialSum['b']], [$first['old_a'], $first['old_b'], $first['db_sum_a'], $first['db_sum_b']]);
    };
    $tests[$case . ' after first update sees current changed row sum'] = static function (TestRunner $t) use ($plan, $firstUpdatedSum): void {
        $second = $plan()['update_log'][1];
        $t->same([$firstUpdatedSum['a'], $firstUpdatedSum['b']], [$second['db_sum_a'], $second['db_sum_b']]);
    };
    $tests[$case . ' final update after log sees all rows changed'] = static function (TestRunner $t) use ($plan, $allUpdatedSum): void {
        $last = $plan()['update_log'][5];
        $t->same([$allUpdatedSum['a'], $allUpdatedSum['b']], [$last['db_sum_a'], $last['db_sum_b']]);
    };
    $tests[$case . ' first delete before log sees all updated rows'] = static function (TestRunner $t) use ($plan, $updatedRows, $allUpdatedSum): void {
        $first = $plan()['delete_log'][0];
        $t->same([$updatedRows[0]['a'], $updatedRows[0]['b'], $allUpdatedSum['a'], $allUpdatedSum['b']], [$first['old_a'], $first['old_b'], $first['db_sum_a'], $first['db_sum_b']]);
    };
    $tests[$case . ' final delete after log sees empty table'] = static function (TestRunner $t) use ($plan): void {
        $last = $plan()['delete_log'][5];
        $t->same([0, 0], [$last['db_sum_a'], $last['db_sum_b']]);
    };
    $tests[$case . ' first insert before log sees empty table'] = static function (TestRunner $t) use ($plan, $insertRows): void {
        $first = $plan()['insert_log'][0];
        $t->same([$insertRows[0]['a'], $insertRows[0]['b'], 0, 0], [$first['new_a'], $first['new_b'], $first['db_sum_a'], $first['db_sum_b']]);
    };
    $tests[$case . ' first insert after log sees inserted row sum'] = static function (TestRunner $t) use ($plan, $insertOneSum): void {
        $second = $plan()['insert_log'][1];
        $t->same([$insertOneSum['a'], $insertOneSum['b']], [$second['db_sum_a'], $second['db_sum_b']]);
    };
    $tests[$case . ' final insert rows preserve statement order'] = static function (TestRunner $t) use ($plan, $insertRows): void {
        $t->same($insertRows, $plan()['final_insert_rows']);
    };
}

return $tests;
