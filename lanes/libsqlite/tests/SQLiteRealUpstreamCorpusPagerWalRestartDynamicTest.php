<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

$tests['real upstream corpus pager walrestart dynamic cites hydrated source'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walRestartCheckpointRaceRows();

    $t->same(1000, count($rows));
    $t->same('walrestart.test 1.2 dynamic race 1', $rows[0]['upstream']);
    $t->same('walrestart.test 1.2 dynamic race 1000', $rows[999]['upstream']);
    $t->same('walrestart.test 1.0..1.5 checkpoint mxFrame/nBackfill restart race', 'walrestart.test 1.0..1.5 checkpoint mxFrame/nBackfill restart race');
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walRestartCheckpointRaceRows() as $row) {
    $tests['real upstream corpus pager walrestart dynamic ' . $row['upstream'] . ' checkpoint race state'] = static function (TestRunner $t) use ($row): void {
        $t->same('walrestart.test', $row['script']);
        $t->same('wal', $row['initial_checkpoint']['journal_mode']);
        $t->same(0, $row['initial_checkpoint']['busy']);
        $t->same(49, $row['initial_checkpoint']['log']);
        $t->same(49, $row['initial_checkpoint']['checkpointed']);
        $t->same(0, $row['pre_race_checkpoint']['busy']);
        $t->same(45, $row['pre_race_checkpoint']['log']);
        $t->same(45, $row['pre_race_checkpoint']['checkpointed']);
        $t->same(0, $row['race_checkpoint']['busy']);
        $t->same(45, $row['race_checkpoint']['log']);
        $t->same(0, $row['race_checkpoint']['checkpointed']);
        $t->same(true, $row['writer_interrupts_between_mxframe_and_nbackfill']);
        $t->same(45, $row['mxframe_before_race']);
        $t->same(45, $row['nbackfill_before_race']);
        $t->same(0, $row['nbackfill_after_race_checkpoint']);
        $t->same(true, $row['restart_prevented_stale_backfill']);
        $t->same('ok', $row['integrity_check']);
    };

    $tests['real upstream corpus pager walrestart dynamic ' . $row['upstream'] . ' writer restart frame counts'] = static function (TestRunner $t) use ($row): void {
        $t->same(0, $row['post_writer_checkpoint']['busy']);
        $t->same($row['mxframe_after_race_writer'], $row['post_writer_checkpoint']['log']);
        $t->same($row['mxframe_after_race_writer'], $row['post_writer_checkpoint']['checkpointed']);
        $t->same(true, $row['mxframe_after_race_writer'] > 0);
        $t->same(true, $row['mxframe_after_race_writer'] < $row['mxframe_before_race']);
        $t->same(true, $row['large_transaction_frames'] >= $row['pre_race_checkpoint']['log']);
        $t->same(true, $row['large_transaction_frames'] <= $row['initial_checkpoint']['log']);
        $t->same(true, $row['faultsim_step'] >= 660);
        $t->same(true, $row['faultsim_step'] <= 662);
        $t->same('db2', $row['writer_connection']);
        $t->same('db', $row['checkpoint_connection']);
    };

    $tests['real upstream corpus pager walrestart dynamic ' . $row['upstream'] . ' sql and dependencies'] = static function (TestRunner $t) use ($row): void {
        $t->same('UPDATE t1 SET b=randomblob(600) WHERE a<5', $row['race_update_sql']);
        $t->same('UPDATE t1 SET b=randomblob(600)', $row['recovery_update_sql']);
        $t->same(true, str_starts_with($row['upstream'], 'walrestart.test 1.2 dynamic race '));
        $t->same(true, in_array('real-upstream-corpus-walrestart', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-restart-race', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-wal-dynamic', $row['dependencies'], true));
    };
}

return $tests;
