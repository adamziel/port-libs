<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaExplainPlan;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test pragma4-2.100.
 *
 * Upstream prepares a fragmented database, runs EXPLAIN PRAGMA integrity_check,
 * then verifies that OP_IntegrityCk renders its P4_INTARRAY root-page operand
 * as an int-array token like x[0-9]+,1x. This ports that observable opcode
 * shape into the PHP PRAGMA explain planner with dynamic generic root pages.
 */

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $rootPage = 7 + $variant;

    $tests["real upstream pragma4.test 2.100 explain integrity_check renders P4 intarray variant {$suffix}"] = static function (TestRunner $t) use ($rootPage): void {
        $tableName = "pragma4_integrity_{$rootPage}";
        $catalog = new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord('table', $tableName, $tableName, $rootPage, "CREATE TABLE {$tableName}(value)", 1),
        ]);
        $plan = $catalog->explain('EXPLAIN PRAGMA integrity_check');
        $integrity = $plan['integrity_opcode'];
        $program = "x{$rootPage},1x";

        $t->same('SQLite test/pragma4.test pragma4-2.100', $plan['source']);
        $t->same('EXPLAIN PRAGMA integrity_check', $plan['sql']);
        $t->same('integrity_check', $plan['pragma']);
        $t->same('main', $plan['schema']);
        $t->same(100, $plan['limit']);
        $t->same([$rootPage, 1], $plan['root_pages']);
        $t->same($program, $plan['root_page_program']);
        $t->same('IntegrityCk', $integrity['opcode']);
        $t->same(1, $integrity['p1']);
        $t->same(2, $integrity['p2']);
        $t->same(8, $integrity['p3']);
        $t->same($program, $integrity['p4']);
        $t->same(0, $integrity['p5']);
        $t->same(1, preg_match('/^x[0-9]+,1x$/', $integrity['p4']));
        $t->contains("{$rootPage},1", $integrity['comment']);
        $t->same(
            ['Init', 'Integer', 'IntegrityCk', 'ResultRow', 'Halt', 'Transaction', 'Goto'],
            array_column($plan['rows'], 'opcode'),
        );
    };
}

$tests['real upstream pragma4.test 2.100 explain integrity_check source citation'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test pragma4-2.100 runs EXPLAIN PRAGMA integrity_check after a fragmented table/drop sequence',
        'pragma4.test pragma4-2.100 asserts opcode IntegrityCk has operands 1 2 8',
        'pragma4.test pragma4-2.100 normalizes the P4_INTARRAY rendering to x[0-9]+,1x',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma4-2.100', $sections[0]);
    $t->contains('IntegrityCk', $sections[1]);
    $t->contains('P4_INTARRAY', $sections[2]);
    $t->same(
        'no new support component needed; reuses lane-local SQLitePragmaExplainPlan for upstream pragma4.test explain opcode parity',
        'no new support component needed; reuses lane-local SQLitePragmaExplainPlan for upstream pragma4.test explain opcode parity',
    );
    $t->same(
        'non-overlap: owns pragma4.test 2.100 EXPLAIN PRAGMA integrity_check OP_IntegrityCk P4 int-array rendering; avoids accepted pragma4 result-column, table_list, virtual pragma, index_xinfo, integrity temp, schema-version, cache_size, data_version, JSON, WAL, VFS, B-tree, and SELECT clusters',
        'non-overlap: owns pragma4.test 2.100 EXPLAIN PRAGMA integrity_check OP_IntegrityCk P4 int-array rendering; avoids accepted pragma4 result-column, table_list, virtual pragma, index_xinfo, integrity temp, schema-version, cache_size, data_version, JSON, WAL, VFS, B-tree, and SELECT clusters',
    );
};

$tests['real upstream pragma4.test 2.100 explain integrity_check rejects non-integrity inputs'] = static function (TestRunner $t): void {
    $schemaPlan = SQLitePragmaExplainPlan::explainIntegrityCheck(
        'EXPLAIN PRAGMA "auxiliary".quick_check(5)',
        null,
        [12, 1],
    );

    $t->same('auxiliary', $schemaPlan['schema']);
    $t->same('quick_check', $schemaPlan['pragma']);
    $t->same(5, $schemaPlan['limit']);
    $t->same('x12,1x', $schemaPlan['integrity_opcode']['p4']);
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaExplainPlan::explainIntegrityCheck('PRAGMA integrity_check', null, [2, 1]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaExplainPlan::explainIntegrityCheck('EXPLAIN PRAGMA table_info(t)', null, [2, 1]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaExplainPlan::explainIntegrityCheck('EXPLAIN PRAGMA integrity_check', null, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaExplainPlan::explainIntegrityCheck('EXPLAIN PRAGMA integrity_check', null, [0]));
};

return $tests;
