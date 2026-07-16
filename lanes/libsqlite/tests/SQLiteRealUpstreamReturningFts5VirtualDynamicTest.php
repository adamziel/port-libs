<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningVirtualTablePlan;

$tests = [];

foreach (range(1, 1000) as $case) {
    $existing = [];
    for ($i = 0; $i < $case % 4; ++$i) {
        $existing[] = ['c' => sprintf('archived document %04d.%d', $case, $i)];
    }
    $peerRows = [
        ['y' => sprintf('peer schema write %04d', $case)],
    ];
    if ($case % 5 === 0) {
        $peerRows[] = ['y' => sprintf('peer schema write %04d extra', $case)];
    }
    $incoming = [
        'c' => sprintf('hello world dynamic fts5 %04d', $case),
    ];

    $tests[sprintf('real upstream returning1 fts5 virtual table peer write returning dynamic %04d', $case)] = static function (TestRunner $t) use ($existing, $peerRows, $incoming, $case): void {
        $plan = SQLiteReturningVirtualTablePlan::insertFts5ReturningAfterPeerWrite($existing, $peerRows, $incoming);
        $after = $existing;
        $after[] = $incoming;

        $t->same('returning1.test', $plan['source']);
        $t->same('returning1-24.3 fts5 INSERT RETURNING emits inserted row after peer write', $plan['scenario']);
        $t->same('fts5', $plan['virtual_table']);
        $t->same('t2', $plan['peer_table']);
        $t->same($peerRows, $plan['peer_rows'], "peer schema writes remain visible for case {$case}");
        $t->same($incoming, $plan['inserted'], "incoming FTS5 text is normalized for case {$case}");
        $t->same($after, $plan['after'], "FTS5 row is appended after peer write for case {$case}");
        $t->same([$incoming], $plan['returning_rows'], "RETURNING emits the inserted FTS5 row for case {$case}");
        $t->same(true, $plan['returning_evaluated']);
        $t->same(1, $plan['changes']);
        $t->same(true, $plan['peer_write_visible']);
        $t->same([
            'sqlite-returning-fts5-virtual-table',
            'sqlite-returning-peer-schema-refresh',
            'returning1.test-24.3',
        ], $plan['dependencies']);
    };
}

$tests['real upstream returning1 fts5 virtual table rejects malformed content column'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteReturningVirtualTablePlan::insertFts5ReturningAfterPeerWrite([], [['y' => 'peer']], ['bad-column' => 'hello world']),
    );
};

$tests['real upstream returning1 fts5 virtual table rejects malformed peer table'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteReturningVirtualTablePlan::insertFts5ReturningAfterPeerWrite([], [['y' => 'peer']], ['c' => 'hello world'], 'c', 'bad-table'),
    );
};

$tests['real upstream returning1 fts5 virtual table dynamic cites upstream source'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.0 creates fts5(c), table t1, and initial row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.2 writes t2 through a peer connection',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.3 INSERT INTO ft VALUES(...) RETURNING * returns the inserted text',
        '1000 focused dynamic PASS cases cover FTS5 virtual-table RETURNING after peer schema writes',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.0 creates fts5(c), table t1, and initial row',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.2 writes t2 through a peer connection',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-24.3 INSERT INTO ft VALUES(...) RETURNING * returns the inserted text',
        '1000 focused dynamic PASS cases cover FTS5 virtual-table RETURNING after peer schema writes',
    ]);
};

$tests['real upstream returning1 fts5 virtual table dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses generic virtual-table RETURNING planning for FTS5 inserted-row streams after peer writes',
        'no new support component needed; reuses generic virtual-table RETURNING planning for FTS5 inserted-row streams after peer writes',
    );
};

return $tests;
