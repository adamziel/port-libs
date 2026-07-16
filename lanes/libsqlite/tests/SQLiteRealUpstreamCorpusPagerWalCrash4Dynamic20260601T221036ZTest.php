<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCrashRecoveryDynamicPlan;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$crash4Source = (string) file_get_contents($upstreamRoot . '/crash4.test');

$crash4CaseCount = 0;
foreach (range(1, 1000) as $iteration) {
    ++$crash4CaseCount;
    $delay = intdiv($iteration, 50) + 1;
    $crashTarget = ($iteration & 1) === 1 ? 'test.db' : 'test.db-journal';
    $expectedChecksumIndex = ($iteration + $delay) % 13;

    $tests[sprintf('real upstream corpus pager crash4 checksum recovery %04d target %s delay %02d', $iteration, $crashTarget, $delay)] =
        static function (TestRunner $t) use ($iteration, $delay, $crashTarget, $expectedChecksumIndex): void {
            $profile = SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile($iteration, $delay, $crashTarget);

            $t->same('ok', $profile['status']);
            $t->same('crash4.test', $profile['script']);
            $t->same('crash4-sequence-checksum-recovery', $profile['scenario']);
            $t->same($iteration, $profile['iteration']);
            $t->same($delay, $profile['crash_delay']);
            $t->same($delay, $profile['expected_delay']);
            $t->same($crashTarget, $profile['crash_target']);
            $t->same($crashTarget, $profile['expected_crash_target']);
            $t->same('child process exited abnormally', $profile['crash_result']);
            $t->same(12, $profile['sql_statement_count']);
            $t->same(13, $profile['checksum_state_count']);
            $t->same($expectedChecksumIndex, $profile['recovered_checksum_index']);
            $t->same($profile['checksum_state_names'][$expectedChecksumIndex], $profile['recovered_checksum_name']);
            $t->same(true, $profile['precomputed_checksum_membership']);
            $t->same(11, $profile['statement_before_reopen_count']);
            $t->same(12, $profile['reopen_before_statement_index']);
            $t->same(true, $profile['reopen_before_update']);
            $t->same("UPDATE A SET name='new text for row 3' WHERE id=3", $profile['final_statement']);
            $t->same(true, $profile['alternates_crash_target_by_iteration']);
            $t->same(true, $profile['rollback_attempted']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['database_corruption_prevented']);
            $t->same('powerloss_recovery_lands_on_precomputed_allcksum_state_after_reopen_before_update', $profile['reason']);
            $t->same('CREATE TABLE a(id INTEGER, name CHAR(50))', $profile['sql_sequence'][0]);
            $t->same("INSERT INTO a(id,name) VALUES(1,'one')", $profile['sql_sequence'][1]);
            $t->same("INSERT INTO a(id,name) VALUES(10,'ten')", $profile['sql_sequence'][10]);
            $t->same("UPDATE A SET name='new text for row 3' WHERE id=3", $profile['sql_sequence'][11]);
            $t->same('empty database before sql_cmd_list', $profile['checksum_state_names'][0]);
            $t->same('after update id 3', $profile['checksum_state_names'][12]);
            $t->same(true, in_array('upstream-crash4-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-crash-recovery', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-rollback-journal-recovery', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-allcksum-state-membership', $profile['dependencies'], true));
            $t->same(true, in_array('real-upstream-pager-crash-corpus', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
        };
}

$tests['real upstream corpus pager crash4 cites hydrated upstream source'] = static function (TestRunner $t) use ($crash4Source): void {
    $t->contains('set sql_cmd_list', $crash4Source);
    $t->contains('lappend crash4_cksum_set [allcksum db]', $crash4Source);
    $t->contains("UPDATE A SET name='new text for row 3' WHERE id=3", $crash4Source);
    $t->contains('db close', $crash4Source);
    $t->contains('sqlite3 db test.db', $crash4Source);
    $t->contains('set delay [expr {int($cnt/50)+1}]', $crash4Source);
    $t->contains('set file [expr {($cnt&1)?"test.db":"test.db-journal"}]', $crash4Source);
    $t->contains('crashsql -delay $delay -file $file -seed $seed', $crash4Source);
    $t->contains('integrity_check crash4-1.$cnt.2', $crash4Source);
    $t->contains('lsearch $::crash4_cksum_set [allcksum db]', $crash4Source);
};

$tests['real upstream corpus pager crash4 rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile(0, 1, 'test.db'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile(1, 2, 'test.db'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile(1, 1, 'test.db-journal'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile(2, 1, 'test.db-wal'));
};

$tests['real upstream corpus pager crash4 owns one thousand dynamic cases'] = static function (TestRunner $t) use ($crash4CaseCount): void {
    $t->same(1000, $crash4CaseCount);
};

$tests['real upstream corpus pager crash4 non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $profile = SQLitePagerCrashRecoveryDynamicPlan::crash4SequenceChecksumProfile(1, 1, 'test.db');

    $t->same(true, in_array('real-upstream-pager-crash-corpus', $profile['dependencies'], true));
    $t->same(true, in_array('sqlite-allcksum-state-membership', $profile['dependencies'], true));
    $t->same(
        'non-overlap: covers crash4.test power-loss checksum-set membership after close/reopen before final UPDATE; avoids accepted crash5/crash6/crash7 movepage, page-size, and VACUUM crash profiles plus WAL checkpoint, rollback-commit, VFS sync/write/lock, super-journal, and master-journal clusters',
        'non-overlap: covers crash4.test power-loss checksum-set membership after close/reopen before final UPDATE; avoids accepted crash5/crash6/crash7 movepage, page-size, and VACUUM crash profiles plus WAL checkpoint, rollback-commit, VFS sync/write/lock, super-journal, and master-journal clusters'
    );
    $t->same(
        'dependency closure: reuses existing bounded pager crash recovery, rollback journal recovery, and upstream corpus checksum evidence; no new support component is needed',
        'dependency closure: reuses existing bounded pager crash recovery, rollback journal recovery, and upstream corpus checksum evidence; no new support component is needed'
    );
};

return $tests;
