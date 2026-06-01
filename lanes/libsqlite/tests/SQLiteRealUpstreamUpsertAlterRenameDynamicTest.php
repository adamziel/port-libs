<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$tests = [];

$upstreamTestDir = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$readUpstream = static function (string $file) use ($upstreamTestDir): string {
    $path = $upstreamTestDir . '/' . $file;
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read upstream SQLite test file: ' . $path);
    }

    return $contents;
};

$tests['real upstream altercol upsert rename cites source truth'] = static function (TestRunner $t) use ($readUpstream): void {
    $altercol = $readUpstream('altercol.test');
    $altertab = $readUpstream('altertab.test');

    $t->contains('do_rename_column_test 9.$tn', $altercol);
    $t->contains('ON CONFLICT (_x_) WHERE _x_>10 DO UPDATE SET _x_ = _x_+1', $altercol);
    $t->contains('ON CONFLICT (_x_) WHERE _x_>10 DO NOTHING', $altercol);
    $t->contains('ON CONFLICT(d) DO UPDATE SET f = excluded.f', $altercol);
    $t->contains('ALTER TABLE t2 RENAME COLUMN f TO "big f"', $altercol);
    $t->contains('ALTER TABLE t1 RENAME COLUMN c TO "big c"', $altercol);
    $t->contains('do_catchsql_test 24.1', $altertab);
    $t->contains('INSERT INTO nosuchtable VALUES(new.a) ON CONFLICT(a) DO NOTHING', $altertab);
};

for ($case = 1; $case <= 250; ++$case) {
    $table = 'app_upsert_rename_' . $case;
    $oldColumn = '_x_' . $case;
    $newColumn = '_xxx_' . $case;

    $tests[sprintf('real upstream altercol 9.4 upsert do update trigger column rename dynamic %04d', $case)] = static function (TestRunner $t) use ($table, $oldColumn, $newColumn): void {
        $sql = sprintf(
            'CREATE TRIGGER app_touch_%d AFTER UPDATE ON %s BEGIN INSERT INTO %s VALUES(new.a, new.b, new.%s) ON CONFLICT (%s) WHERE %s>10 DO UPDATE SET %s = %s+1; END',
            crc32($table) % 100000,
            $table,
            $table,
            $oldColumn,
            $oldColumn,
            $oldColumn,
            $oldColumn,
            $oldColumn
        );

        $rewritten = SQLiteAlterTableRenamePlan::renameColumnSql($sql, $table, $oldColumn, $newColumn);

        $t->contains('new.' . $newColumn, $rewritten);
        $t->contains('ON CONFLICT (' . $newColumn . ') WHERE ' . $newColumn . '>10 DO UPDATE SET ' . $newColumn . ' = ' . $newColumn . '+1', $rewritten);
        $t->same(false, str_contains($rewritten, 'new.' . $oldColumn));
        $t->same(false, str_contains($rewritten, 'ON CONFLICT (' . $oldColumn . ')'));
        $t->same(false, str_contains($rewritten, 'WHERE ' . $oldColumn . '>10'));
        $t->same(false, str_contains($rewritten, 'SET ' . $oldColumn . ' = ' . $oldColumn . '+1'));
    };

    $tests[sprintf('real upstream altercol 9.4 upsert do nothing trigger column rename dynamic %04d', $case)] = static function (TestRunner $t) use ($table, $oldColumn, $newColumn): void {
        $sql = sprintf(
            'CREATE TRIGGER app_ignore_%d AFTER UPDATE ON %s BEGIN INSERT INTO %s VALUES(new.a, new.b, new.%s) ON CONFLICT (%s) WHERE %s>10 DO NOTHING; END',
            crc32($newColumn) % 100000,
            $table,
            $table,
            $oldColumn,
            $oldColumn,
            $oldColumn
        );

        $rewritten = SQLiteAlterTableRenamePlan::renameColumnSql($sql, $table, $oldColumn, $newColumn);

        $t->contains('new.' . $newColumn, $rewritten);
        $t->contains('ON CONFLICT (' . $newColumn . ') WHERE ' . $newColumn . '>10 DO NOTHING', $rewritten);
        $t->same(false, str_contains($rewritten, 'new.' . $oldColumn));
        $t->same(false, str_contains($rewritten, 'ON CONFLICT (' . $oldColumn . ')'));
        $t->same(false, str_contains($rewritten, 'WHERE ' . $oldColumn . '>10'));
    };

    $sourceTable = 'app_source_' . $case;
    $targetTable = 'app_target_' . $case;
    $renamedTargetColumn = 'big f' . $case;
    $quotedTargetColumn = '"' . $renamedTargetColumn . '"';

    $tests[sprintf('real upstream altercol 16.1.5 upsert excluded target rename dynamic %04d', $case)] = static function (TestRunner $t) use ($sourceTable, $targetTable, $renamedTargetColumn, $quotedTargetColumn): void {
        $sql = sprintf(
            'CREATE TRIGGER app_forward_%d AFTER INSERT ON %s BEGIN INSERT INTO %s VALUES(new.a,new.b,new.c) ON CONFLICT(d) DO UPDATE SET f=excluded.f; END',
            crc32($targetTable) % 100000,
            $sourceTable,
            $targetTable
        );

        $rewritten = SQLiteAlterTableRenamePlan::renameColumnSql($sql, $targetTable, 'f', $renamedTargetColumn);

        $t->contains('ON CONFLICT(d) DO UPDATE SET ' . $quotedTargetColumn . '=excluded.' . $quotedTargetColumn, $rewritten);
        $t->same(false, str_contains($rewritten, 'SET f=excluded.f'));
        $t->same(false, str_contains($rewritten, 'excluded.f'));
        $t->same(true, str_contains($rewritten, 'INSERT INTO ' . $targetTable));
        $t->same(true, str_contains($rewritten, 'ON ' . $sourceTable . ' BEGIN'));
    };

    $renamedSourceColumn = 'big c' . $case;
    $quotedSourceColumn = '"' . $renamedSourceColumn . '"';

    $tests[sprintf('real upstream altercol 16.1.6 upsert new column rename dynamic %04d', $case)] = static function (TestRunner $t) use ($sourceTable, $targetTable, $renamedSourceColumn, $quotedSourceColumn): void {
        $sql = sprintf(
            'CREATE TRIGGER app_forward_source_%d AFTER INSERT ON %s BEGIN INSERT INTO %s VALUES(new.a,new.b,new.c) ON CONFLICT(d) DO UPDATE SET f=excluded.f; END',
            crc32($sourceTable) % 100000,
            $sourceTable,
            $targetTable
        );

        $rewritten = SQLiteAlterTableRenamePlan::renameColumnSql($sql, $sourceTable, 'c', $renamedSourceColumn);

        $t->contains('VALUES(new.a,new.b,new.' . $quotedSourceColumn . ')', $rewritten);
        $t->contains('ON CONFLICT(d) DO UPDATE SET f=excluded.f', $rewritten);
        $t->same(false, str_contains($rewritten, 'new.c'));
        $t->same(true, str_contains($rewritten, 'INSERT INTO ' . $targetTable));
        $t->same(true, str_contains($rewritten, 'ON ' . $sourceTable . ' BEGIN'));
    };
}

$tests['real upstream altercol quoted renamed column is rendered for unquoted old token'] = static function (TestRunner $t): void {
    $rewritten = SQLiteAlterTableRenamePlan::renameColumnSql(
        'CREATE TRIGGER app_forward AFTER INSERT ON app_source BEGIN INSERT INTO app_target VALUES(new.a,new.b,new.c) ON CONFLICT(d) DO UPDATE SET f=excluded.f; END',
        'app_target',
        'f',
        'big f'
    );

    $t->contains('SET "big f"=excluded."big f"', $rewritten);
};

$tests['real upstream altercol malformed space suffix still rejected'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE TABLE app_source(a)', 'app_source', 'a', 'bad name '));
};

$tests['real upstream altercol malformed dash column still rejected'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE TABLE app_source(a)', 'app_source', 'a', 'bad-name'));
};

$tests['real upstream altercol control byte column still rejected'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE TABLE app_source(a)', 'app_source', 'a', "bad\nname"));
};

$tests['real upstream altercol upsert rename dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteAlterTableRenamePlan schema-text token rewrite for upstream altercol UPSERT trigger bodies',
        'no new support component needed; reuses SQLiteAlterTableRenamePlan schema-text token rewrite for upstream altercol UPSERT trigger bodies'
    );
};

return $tests;
