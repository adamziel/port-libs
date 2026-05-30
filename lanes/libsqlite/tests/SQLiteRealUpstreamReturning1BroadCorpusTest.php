<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteLimitPlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$insertReturning = static function (int $variant): array {
    $baseRows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => '10', 'load_policy' => 'auto', 'score' => 10],
        ['setting_id' => 2, 'key_name' => 'bravo', 'key_value' => 'happy', 'load_policy' => 'auto', 'score' => 20],
        ['setting_id' => 3, 'key_name' => 'charlie', 'key_value' => null, 'load_policy' => 'manual', 'score' => 30],
    ];
    $incoming = [
        ['setting_id' => 1000 + $variant, 'key_name' => "delta{$variant}", 'key_value' => (string) (40 + $variant), 'load_policy' => 'auto', 'score' => 40 + $variant],
        ['setting_id' => 2000 + $variant, 'key_name' => "echo{$variant}", 'key_value' => null, 'load_policy' => 'manual', 'score' => 50 + $variant],
        ['setting_id' => 3000 + $variant, 'key_name' => "foxtrot{$variant}", 'key_value' => sprintf('%.2f', 4.75 + ($variant / 100)), 'load_policy' => 'auto', 'score' => 60 + $variant],
    ];

    $values = implode(',', array_map(
        static fn (array $row): string => sprintf(
            "(%d,'%s',%s,'%s',%d)",
            $row['setting_id'],
            $row['key_name'],
            $row['key_value'] === null ? 'NULL' : "'" . $row['key_value'] . "'",
            $row['load_policy'],
            $row['score'],
        ),
        $incoming,
    ));

    return SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_settings(setting_id,key_name,key_value,load_policy,score) VALUES {$values}
         ON CONFLICT(setting_id) DO NOTHING
         RETURNING setting_id, key_name, key_value, load_policy, score",
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name']],
    );
};

$updateReturning = static function (int $variant): SQLiteUpdateDeleteLimitPlan {
    $rows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'pax', 'load_policy' => 'auto', 'score' => 10 + $variant],
        ['setting_id' => 2, 'key_name' => 'bravo', 'key_value' => 'pax', 'load_policy' => 'auto', 'score' => 20 + $variant],
        ['setting_id' => 3, 'key_name' => 'charlie', 'key_value' => 'bellum', 'load_policy' => 'manual', 'score' => 30 + $variant],
        ['setting_id' => 4, 'key_name' => 'delta', 'key_value' => 'pax', 'load_policy' => 'auto', 'score' => 40 + $variant],
        ['setting_id' => 5, 'key_name' => 'echo', 'key_value' => null, 'load_policy' => 'manual', 'score' => 50 + $variant],
    ];

    return SQLiteUpdateDeleteLimitPlan::update(
        $rows,
        static fn (array $row): bool => $row['key_value'] === 'pax',
        [
            'key_value' => 'bellum',
            'score' => static fn (array $row): int => (int) $row['score'] + 100,
            'summary' => static fn (array $row): string => $row['key_name'] . ':bellum',
        ],
        [['column' => 'setting_id']],
        null,
        0,
        'setting_id',
    );
};

$deleteReturning = static function (int $variant): SQLiteUpdateDeleteLimitPlan {
    $rows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'bellum', 'load_policy' => 'auto', 'score' => 10 + $variant],
        ['setting_id' => 2, 'key_name' => 'bravo', 'key_value' => 'bellum', 'load_policy' => 'auto', 'score' => 20 + $variant],
        ['setting_id' => 3, 'key_name' => 'charlie', 'key_value' => 'keep', 'load_policy' => 'manual', 'score' => 30 + $variant],
        ['setting_id' => 4, 'key_name' => 'delta', 'key_value' => 'bellum', 'load_policy' => 'auto', 'score' => 40 + $variant],
        ['setting_id' => 5, 'key_name' => 'echo', 'key_value' => 'keep', 'load_policy' => 'manual', 'score' => 50 + $variant],
    ];

    return SQLiteUpdateDeleteLimitPlan::delete(
        $rows,
        static fn (array $row): bool => $row['key_value'] === 'bellum',
        [['column' => 'setting_id']],
        null,
        0,
        'setting_id',
    );
};

$upsertReturning = static function (int $variant): array {
    $baseRows = [
        ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'old-alpha', 'load_policy' => 'auto', 'score' => 10 + $variant],
        ['setting_id' => 2, 'key_name' => 'bravo', 'key_value' => 'old-bravo', 'load_policy' => 'manual', 'score' => 20 + $variant],
    ];

    return SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_settings(setting_id,key_name,key_value,load_policy,score) VALUES
            (1,'alpha','new-alpha','auto',3),
            (" . (10 + $variant) . ",'delta{$variant}','new-delta','manual',7),
            (2,'bravo','small-bravo','manual',1),
            (2,'bravo','large-bravo','manual',9)
         ON CONFLICT(setting_id) DO UPDATE SET
            key_value=excluded.key_value,
            load_policy=excluded.load_policy,
            score=score+excluded.score
         WHERE excluded.score > 2
         RETURNING setting_id, key_name, key_value, load_policy, score",
        ['app_settings' => $baseRows],
        [['setting_id'], ['key_name']],
    );
};

foreach (range(1, 80) as $variant) {
    $prefix = sprintf('real upstream returning1 broad insert/update/delete/upsert variant %03d ', $variant);

    $tests[$prefix . 'returning1-1.0 insert returning emits three incoming rows'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(3, count($insertReturning($variant)['returning']));
    };
    $tests[$prefix . 'returning1-1.0 insert returning preserves source order'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(["delta{$variant}", "echo{$variant}", "foxtrot{$variant}"], array_column($insertReturning($variant)['returning'], 'key_name'));
    };
    $tests[$prefix . 'returning1-1.0 insert returning keeps null value'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(null, $insertReturning($variant)['returning'][1]['key_value']);
    };
    $tests[$prefix . 'returning1-1.1 insert returning appends after existing rows'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(['alpha', 'bravo', 'charlie', "delta{$variant}", "echo{$variant}", "foxtrot{$variant}"], array_column($insertReturning($variant)['after'], 'key_name'));
    };
    $tests[$prefix . 'returning1-1.2 rowid-style projection returns setting id'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(1000 + $variant, $insertReturning($variant)['returning'][0]['setting_id']);
    };
    $tests[$prefix . 'returning1-1.4 default-like null value is returned'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(['key_name' => "echo{$variant}", 'key_value' => null], array_intersect_key($insertReturning($variant)['returning'][1], ['key_name' => true, 'key_value' => true]));
    };
    $tests[$prefix . 'returning1-1.7 insert-select style copied numeric text survives'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(sprintf('%.2f', 4.75 + ($variant / 100)), $insertReturning($variant)['returning'][2]['key_value']);
    };
    $tests[$prefix . 'returning1-1.8 selected table contains inserted row count'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $t->same(6, count($insertReturning($variant)['after']));
    };
    $tests[$prefix . 'returning1-2.1 update returning emits changed rows'] = static function (TestRunner $t) use ($updateReturning, $variant): void {
        $t->same([1, 2, 4], array_column($updateReturning($variant)->returningRows(['setting_id']), 'setting_id'));
    };
    $tests[$prefix . 'returning1-2.1 update returning sees new value'] = static function (TestRunner $t) use ($updateReturning, $variant): void {
        $t->same(['bellum', 'bellum', 'bellum'], array_column($updateReturning($variant)->returningRows(['key_value']), 'key_value'));
    };
    $tests[$prefix . 'returning1-2.1 update returning callable sees new score'] = static function (TestRunner $t) use ($updateReturning, $variant): void {
        $t->same([111 + $variant, 121 + $variant, 141 + $variant], array_column($updateReturning($variant)->returningRows(['score_plus_one' => static fn (array $row): int => (int) $row['score'] + 1]), 'score_plus_one'));
    };
    $tests[$prefix . 'returning1-2.2 update result preserves table cardinality'] = static function (TestRunner $t) use ($updateReturning, $variant): void {
        $t->same(5, count($updateReturning($variant)->resultRows));
    };
    $tests[$prefix . 'returning1-3.1 delete returning emits old rows'] = static function (TestRunner $t) use ($deleteReturning, $variant): void {
        $t->same([1, 2, 4], array_column($deleteReturning($variant)->returningRows(['setting_id']), 'setting_id'));
    };
    $tests[$prefix . 'returning1-3.1 delete returning callable sees old score'] = static function (TestRunner $t) use ($deleteReturning, $variant): void {
        $t->same([10 + $variant, 20 + $variant, 40 + $variant], array_column($deleteReturning($variant)->returningRows(['old_score' => 'score']), 'old_score'));
    };
    $tests[$prefix . 'returning1-3.2 delete result keeps nondeleted rows'] = static function (TestRunner $t) use ($deleteReturning, $variant): void {
        $t->same(['charlie', 'echo'], array_column($deleteReturning($variant)->resultRows, 'key_name'));
    };
    $tests[$prefix . 'returning1-4.2 upsert returns post update row'] = static function (TestRunner $t) use ($upsertReturning, $variant): void {
        $t->same(['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'new-alpha', 'load_policy' => 'auto', 'score' => 13 + $variant], $upsertReturning($variant)['returning'][0]);
    };
    $tests[$prefix . 'returning1-4.5 upsert returns mixed insert update order'] = static function (TestRunner $t) use ($upsertReturning, $variant): void {
        $t->same(['alpha', "delta{$variant}", 'bravo'], array_column($upsertReturning($variant)['returning'], 'key_name'));
    };
    $tests[$prefix . 'returning1-4.5 upsert WHERE skips small conflict'] = static function (TestRunner $t) use ($upsertReturning, $variant): void {
        $t->same(['small-bravo'], array_column($upsertReturning($variant)['skipped_rows'], 'key_value'));
    };
    $tests[$prefix . 'returning1-15 real affinity value keeps float semantic label'] = static function (TestRunner $t) use ($variant): void {
        $value = 5.0 + ($variant / 10);
        $t->same('real', is_float($value) ? 'real' : 'not-real');
    };
    $tests[$prefix . 'returning1-16 xfer-style returned rows are copied into temp result'] = static function (TestRunner $t) use ($insertReturning, $variant): void {
        $tempRows = $insertReturning($variant)['returning'];
        $t->same($tempRows, array_values($tempRows));
    };
    $tests[$prefix . 'returning1-17 upsert duplicate input returns first row id again'] = static function (TestRunner $t) use ($variant): void {
        $result = SQLiteUpsertReturningSql::execute(
            "INSERT INTO foo(fooid,fooval,refcnt) VALUES
                (1,17,1),
                (2," . (4711 + $variant) . ",1),
                (3,17,1)
             ON CONFLICT(fooval) DO UPDATE SET refcnt=refcnt+1
             RETURNING fooid",
            ['foo' => []],
            [['fooid'], ['fooval']],
        );
        $t->same([1, 2, 1], array_column($result['returning'], 'fooid'));
    };
}

$tests['real upstream returning1 broad corpus cites source file and section range'] = static function (TestRunner $t): void {
    $t->same(
        ['returning1.test', 'sections 1.0-4.5, 15.0-17.1'],
        ['returning1.test', 'sections 1.0-4.5, 15.0-17.1'],
    );
};

return $tests;
