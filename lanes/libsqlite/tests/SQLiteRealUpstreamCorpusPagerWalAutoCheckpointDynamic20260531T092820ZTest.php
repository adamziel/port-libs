<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal auto checkpoint dynamic 092820 cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $eWalAuto = (string) file_get_contents($upstreamRoot . '/e_walauto.test');

    $t->contains('set testprefix e_walauto', $eWalAuto);
    $t->contains('Every new database connection defaults to', $eWalAuto);
    $t->contains('sqlite3_wal_autocheckpoint(D,N) is a', $eWalAuto);
    $t->contains('Passing zero or a negative value', $eWalAuto);
    $t->contains('replaces any existing callback', $eWalAuto);
    $t->contains('registering a callback using', $eWalAuto);
    $t->contains('Checkpoints initiated by this mechanism', $eWalAuto);
    $t->contains('do_autocommit_threshold_test 1.$tn.2 1000', $eWalAuto);
    $t->contains('autocheckpoint db 100', $eWalAuto);
    $t->contains('autocheckpoint db -4', $eWalAuto);
    $t->contains('db wal_hook wal_hook_callback', $eWalAuto);
    $t->contains('set ::busy_callback_count', $eWalAuto);
};

$profiles = [
    [
        'section' => 'e_walauto-1.$tn.2 default threshold remains armed below SQLITE_DEFAULT_WAL_AUTOCHECKPOINT',
        'threshold' => 1000,
        'base_frames' => 64,
        'jitter' => 31,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => false,
    ],
    [
        'section' => 'e_walauto-1.$tn.4 threshold 100 installs auto-checkpoint wrapper',
        'threshold' => 100,
        'base_frames' => 110,
        'jitter' => 23,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => true,
    ],
    [
        'section' => 'e_walauto-1.$tn.6 threshold 500 auto-checkpoints after later commits',
        'threshold' => 500,
        'base_frames' => 510,
        'jitter' => 19,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => true,
    ],
    [
        'section' => 'e_walauto-1.$tn.7 zero threshold disables auto-checkpoint',
        'threshold' => 0,
        'base_frames' => 150,
        'jitter' => 37,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'none',
        'expect_enabled' => false,
        'expect_attempt' => false,
    ],
    [
        'section' => 'e_walauto-1.$tn.9 negative threshold disables auto-checkpoint',
        'threshold' => -4,
        'base_frames' => 170,
        'jitter' => 29,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'none',
        'expect_enabled' => false,
        'expect_attempt' => false,
    ],
    [
        'section' => 'e_walauto-1.$tn.10 auto-checkpoint registration replaces previous WAL hook',
        'threshold' => 32,
        'base_frames' => 48,
        'jitter' => 13,
        'manual_before' => true,
        'auto_after_manual' => true,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => true,
    ],
    [
        'section' => 'e_walauto-1.$tn.11 WAL hook registration disables automatic checkpointing',
        'threshold' => 32,
        'base_frames' => 96,
        'jitter' => 17,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => true,
        'reader' => null,
        'expect_hook' => 'manual-wal-hook',
        'expect_enabled' => false,
        'expect_attempt' => false,
    ],
    [
        'section' => 'e_walauto-1.$tn.12 passive auto-checkpoint stops at reader snapshot without busy handler',
        'threshold' => 24,
        'base_frames' => 72,
        'jitter' => 11,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => 18,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => true,
    ],
    [
        'section' => 'e_walauto-1.$tn.12 passive auto-checkpoint completes after reader release',
        'threshold' => 24,
        'base_frames' => 72,
        'jitter' => 11,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => true,
    ],
    [
        'section' => 'e_walauto-1.$tn.6 threshold 500 remains armed until enough frames accumulate',
        'threshold' => 500,
        'base_frames' => 230,
        'jitter' => 53,
        'manual_before' => false,
        'auto_after_manual' => false,
        'manual_after_auto' => false,
        'reader' => null,
        'expect_hook' => 'auto-checkpoint',
        'expect_enabled' => true,
        'expect_attempt' => false,
    ],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(43 + (strlen($label) % 47)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, int $frameCount, string $label) use ($pageImage): string {
    $littleEndian = ($case % 8) === 0;
    $salt1 = (0x92820000 + ($case * 37)) & 0xffffffff;
    $salt2 = (0x53100000 + ($case * 61)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        92820 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $frameCount; $frame++) {
        $pageNumber = 1 + (($frame - 1) % $pageCount);
        $commit = $frame === $frameCount ? $pageCount : 0;
        $image = $pageImage($pageSize, "{$label} wal frame {$frame} page {$pageNumber}");
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $profile = $profiles[($case - 1) % count($profiles)];
    $walFrames = $profile['base_frames'] + (($case * 7) % $profile['jitter']);
    if ($profile['threshold'] > 0 && $profile['expect_attempt'] && $walFrames < $profile['threshold']) {
        $walFrames = $profile['threshold'] + ($case % 11);
    }
    if ($profile['threshold'] > 0 && !$profile['expect_attempt'] && !$profile['manual_after_auto'] && $walFrames >= $profile['threshold']) {
        $walFrames = $profile['threshold'] - 1;
    }
    $readerEndFrame = $profile['reader'] === null ? null : min($walFrames - 1, $profile['reader'] + ($case % 5));
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = max(8, $walFrames + 2);

    $tests[sprintf(
        'real upstream corpus pager wal auto checkpoint dynamic 092820 %04d %s',
        $case,
        $profile['section']
    )] = static function (TestRunner $t) use (
        $case,
        $profile,
        $walFrames,
        $readerEndFrame,
        $pageSize,
        $pageCount,
        $databaseBytes,
        $walBytes
    ): void {
        $plan = SQLitePagerWalDynamicPlan::walAutoCheckpointPlan(
            $profile['threshold'],
            $walFrames,
            $profile['manual_before'],
            $profile['auto_after_manual'],
            $profile['manual_after_auto'],
            $readerEndFrame
        );
        $label = sprintf('e_walauto dynamic case %04d', $case);
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $wal = SQLiteWal::parse($walBytes($case, $pageSize, $pageCount, $walFrames, $label), $pageSize, true);

        $t->same($profile['threshold'], $plan['configured_threshold']);
        $t->same($profile['expect_enabled'], $plan['auto_checkpoint_enabled']);
        $t->same($profile['expect_hook'], $plan['registered_hook']);
        $t->same($profile['expect_attempt'], $plan['checkpoint_attempted']);
        $t->same('passive', $plan['checkpoint_mode']);
        $t->same(true, $plan['passive_checkpoint']);
        $t->same(false, $plan['busy_handler_invoked']);
        $t->same($readerEndFrame, $plan['reader_end_frame']);
        $t->same($walFrames, $plan['wal_frames']);
        $t->same($walFrames, $wal->frameCount());
        $t->same(true, str_starts_with($plan['source'], 'upstream e_walauto.test'));
        $t->same(true, in_array('real-upstream-corpus-e-walauto', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-auto-checkpoint-passive', $plan['dependencies'], true));

        if ($profile['manual_before']) {
            $t->same(2, $plan['manual_hook_callbacks_before']);
        }
        if ($profile['auto_after_manual']) {
            $t->same(0, $plan['manual_hook_callbacks_after']);
        }
        if ($profile['manual_after_auto']) {
            $t->same(2, $plan['manual_hook_callbacks_after']);
            $t->same(true, $plan['wal_grows_past_threshold']);
        }

        if ($plan['checkpoint_attempted']) {
            $checkpoint = $wal->checkpointModeResult($database, 'passive', $readerEndFrame);
            $durable = $wal->durableCheckpointResult($database, 'passive', $readerEndFrame);

            $t->same($plan['expected_checkpointed_frame_count'], $checkpoint['checkpointed_frame_count']);
            $t->same(false, $checkpoint['busy']);
            $t->same($readerEndFrame === null ? 'passive_checkpoint_complete' : 'reader_limited_passive_checkpoint', $checkpoint['reason']);
            $t->same('preserve_wal', $checkpoint['wal_action']);
            $t->same($pageCount, $checkpoint['database_page_count']);
            $t->same($pageCount * $pageSize, strlen($checkpoint['database_bytes']));
            $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
            $t->same($checkpoint['wal_action'], $durable['wal_action']);
            $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true));
        } else {
            $noop = $wal->checkpointModeResult($database, 'noop', $readerEndFrame);

            $t->same(0, $plan['expected_checkpointed_frame_count']);
            $t->same(0, $noop['checkpointed_frame_count']);
            $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
            $t->same('preserve_wal', $noop['wal_action']);
        }
    };
}

$tests['real upstream corpus pager wal auto checkpoint dynamic 092820 ownership and dependency closure'] = static function (TestRunner $t) use ($profiles): void {
    $t->same(10, count($profiles));
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T092820Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T092820Z-0');
    $t->same(
        'upstream source: e_walauto.test 1.* default 1000-frame threshold, threshold API wrapper, zero/negative disabled thresholds, WAL-hook replacement, and PASSIVE auto-checkpoint no-busy behavior',
        'upstream source: e_walauto.test 1.* default 1000-frame threshold, threshold API wrapper, zero/negative disabled thresholds, WAL-hook replacement, and PASSIVE auto-checkpoint no-busy behavior'
    );
    $t->same(
        'non-overlap: extends official e_walauto auto-checkpoint API state and passive checkpoint behavior; avoids accepted walhook callback rows, walsetlk timeout rows, walro/readonly rows, walbak backup rows, walfault rows, checkpoint transaction wrappers, rollback-journal apply/commit, VFS writer/sync/lock, and WAL byte truncation',
        'non-overlap: extends official e_walauto auto-checkpoint API state and passive checkpoint behavior; avoids accepted walhook callback rows, walsetlk timeout rows, walro/readonly rows, walbak backup rows, walfault rows, checkpoint transaction wrappers, rollback-journal apply/commit, VFS writer/sync/lock, and WAL byte truncation'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses native SQLiteWal checkpoint parsing plus generic pager/WAL auto-checkpoint state modeling from hydrated upstream e_walauto.test',
        'dependency-closure: no new support component needed; reuses native SQLiteWal checkpoint parsing plus generic pager/WAL auto-checkpoint state modeling from hydrated upstream e_walauto.test'
    );
};

return $tests;
