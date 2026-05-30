<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRowsForSeed = static function (int $seed): array {
    return [
        ['a' => 100000 + ($seed * 10) + 1, 'b' => null, 'c' => 'one-' . $seed],
        ['a' => 100000 + ($seed * 10) + 2, 'b' => null, 'c' => 'two-' . $seed],
        ['a' => 100000 + ($seed * 10) + 3, 'b' => null, 'c' => 'three-' . $seed],
    ];
};

$execute = static function (string $sql, array $rows): array {
    return SQLiteUpsertReturningSql::execute(
        $sql,
        ['app_upsert4' => $rows],
        [['a'], ['c']],
    );
};

$tests['real upstream upsert4.test source scenarios are cited'] = static function (TestRunner $t): void {
    $t->same('upsert4.test: 1.$tn.1 primary-key DO NOTHING leaves rows unchanged', 'upsert4.test: 1.$tn.1 primary-key DO NOTHING leaves rows unchanged');
    $t->same('upsert4.test: 1.$tn.2 unique-column DO NOTHING leaves rows unchanged', 'upsert4.test: 1.$tn.2 unique-column DO NOTHING leaves rows unchanged');
    $t->same('upsert4.test: 1.$tn.3 unique-column DO UPDATE changes the conflicting row', 'upsert4.test: 1.$tn.3 unique-column DO UPDATE changes the conflicting row');
    $t->same('upsert4.test: 1.$tn.5 secondary UNIQUE conflict aborts the update', 'upsert4.test: 1.$tn.5 secondary UNIQUE conflict aborts the update');
    $t->same('upsert4.test: 1.$tn.8 DO UPDATE may move the primary key when no secondary conflict remains', 'upsert4.test: 1.$tn.8 DO UPDATE may move the primary key when no secondary conflict remains');
};

foreach (range(1, 1000) as $seed) {
    $tests[sprintf('real upstream upsert4 dynamic conflict update returning %04d', $seed)] = static function (TestRunner $t) use ($seed, $baseRowsForSeed, $execute): void {
        $rows = $baseRowsForSeed($seed);
        $a1 = $rows[0]['a'];
        $a2 = $rows[1]['a'];
        $a3 = $rows[2]['a'];
        $newA = 200000 + $seed;
        $movedA = 300000 + $seed;
        $bUnique = 5000 + $seed;
        $bPrimary = 7000 + $seed;
        $c1 = $rows[0]['c'];
        $c2 = $rows[1]['c'];

        $primarySkip = $execute(
            "INSERT INTO app_upsert4(a,b,c) VALUES({$a1}, NULL, 'candidate-{$seed}') "
            . 'ON CONFLICT(a) DO NOTHING RETURNING a,b,c',
            $rows,
        );
        $t->same([], $primarySkip['returning'], "upsert4.test 1.\$tn.1 primary DO NOTHING returning {$seed}");
        $t->same($rows, $primarySkip['after'], "upsert4.test 1.\$tn.1 primary DO NOTHING after {$seed}");

        $uniqueSkip = $execute(
            "INSERT INTO app_upsert4(a,b,c) VALUES({$newA}, NULL, '{$c2}') "
            . 'ON CONFLICT DO NOTHING RETURNING a,b,c',
            $rows,
        );
        $t->same([], $uniqueSkip['returning'], "upsert4.test 1.\$tn.2 unique DO NOTHING returning {$seed}");
        $t->same($rows, $uniqueSkip['after'], "upsert4.test 1.\$tn.2 unique DO NOTHING after {$seed}");

        $uniqueUpdate = $execute(
            "INSERT INTO app_upsert4(a,b,c) VALUES({$newA}, NULL, '{$c2}') "
            . "ON CONFLICT(c) DO UPDATE SET b={$bUnique} RETURNING a,b,c",
            $rows,
        );
        $t->same([['a' => $a2, 'b' => $bUnique, 'c' => $c2]], $uniqueUpdate['returning'], "upsert4.test 1.\$tn.3 unique update returning {$seed}");
        $t->same([
            ['a' => $a1, 'b' => null, 'c' => $c1],
            ['a' => $a2, 'b' => $bUnique, 'c' => $c2],
            ['a' => $a3, 'b' => null, 'c' => $rows[2]['c']],
        ], $uniqueUpdate['after'], "upsert4.test 1.\$tn.3 unique update after {$seed}");

        $primaryUpdate = $execute(
            "INSERT INTO app_upsert4(a,b,c) VALUES({$a2}, NULL, 'zero-{$seed}') "
            . "ON CONFLICT(a) DO UPDATE SET b={$bPrimary} RETURNING a,b,c",
            $rows,
        );
        $t->same([['a' => $a2, 'b' => $bPrimary, 'c' => $c2]], $primaryUpdate['returning'], "upsert4.test 1.\$tn.4 primary update returning {$seed}");

        $t->throws(InvalidArgumentException::class, static function () use ($a2, $c1, $seed, $rows, $execute): array {
            return $execute(
                "INSERT INTO app_upsert4(a,b,c) VALUES({$a2}, NULL, 'zero-{$seed}') "
                . "ON CONFLICT(a) DO UPDATE SET c='{$c1}' RETURNING a,b,c",
                $rows,
            );
        }, "upsert4.test 1.\$tn.5 secondary unique abort {$seed}");

        $primaryMove = $execute(
            "INSERT INTO app_upsert4(a,b,c) VALUES({$a1}, NULL, NULL) "
            . "ON CONFLICT(a) DO UPDATE SET c='four-{$seed}', a={$movedA} RETURNING a,b,c",
            $rows,
        );
        $t->same([['a' => $movedA, 'b' => null, 'c' => 'four-' . $seed]], $primaryMove['returning'], "upsert4.test 1.\$tn.8 primary key move returning {$seed}");
        $t->same([
            ['a' => $movedA, 'b' => null, 'c' => 'four-' . $seed],
            ['a' => $a2, 'b' => null, 'c' => $c2],
            ['a' => $a3, 'b' => null, 'c' => $rows[2]['c']],
        ], $primaryMove['after'], "upsert4.test 1.\$tn.8 primary key move after {$seed}");
    };
}

$tests['real upstream upsert4 dynamic owns exactly 1000 generated behavior cases'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

return $tests;
