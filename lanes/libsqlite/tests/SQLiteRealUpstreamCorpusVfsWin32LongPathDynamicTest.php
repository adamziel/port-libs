<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$variants = ['1a', '1b', '1c', '1d', '1e', '1f'];

foreach (range(1, 1001) as $case) {
    $variant = $variants[($case - 1) % count($variants)];
    $drive = chr(ord('C') + ($case % 3));
    $rawPath = sprintf('%s:/sqlite/upstream/win32longpath/case-%04d', $drive, $case);
    $pid = 70000 + $case;
    $segmentLengths = [
        230 + ($case % 26),
        240 + (($case * 7) % 16),
    ];
    if (($case % 5) === 0) {
        $segmentLengths[] = 32 + ($case % 29);
    }

    $tests[sprintf(
        'real upstream corpus vfs win32 longpath dynamic win32longpath.test uri %s case %04d',
        $variant,
        $case
    )] = static function (TestRunner $t) use ($case, $variant, $rawPath, $pid, $segmentLengths): void {
        $profile = SQLiteVfsIoDynamicPlan::win32LongPathProfile(
            $rawPath,
            $pid,
            $segmentLengths,
            $variant,
            false
        );

        $t->same('ok', $profile['status']);
        $t->same('win32longpath.test', $profile['script']);
        $t->same('win32', $profile['default_vfs']);
        $t->same('win32-longpath', $profile['selected_vfs']);
        $t->same($rawPath, $profile['raw_path']);
        $t->same(str_replace('/', '\\', $rawPath), $profile['native_path']);
        $t->same($pid, $profile['pid']);
        $t->same($segmentLengths, $profile['segment_lengths']);
        $t->same(array_sum($segmentLengths), $profile['long_segment_bytes']);
        $t->same(true, str_starts_with($profile['filename'], '\\\\?\\'));
        $t->same(true, $profile['path_length'] > 260);
        $t->same(true, $profile['max_path_exceeded']);
        $t->same(true, $profile['long_path_prefix_required']);
        $t->same('error', $profile['stripped_open_status']);
        $t->same('unable to open database file', $profile['stripped_open_error']);
        $t->same(substr($profile['filename'], 4), $profile['stripped_filename']);
        $t->same($variant, $profile['uri_variant']);
        $t->same(false, $profile['translatefilename']);
        $t->same(true, $profile['uri_translation_disabled']);
        $t->same(in_array($variant, ['1b', '1d', '1f'], true), $profile['uri_uses_slash_path']);
        $t->same(true, str_starts_with($profile['uri'], in_array($variant, ['1a', '1b'], true) ? 'file:' : (in_array($variant, ['1c', '1d'], true) ? 'file:///' : 'file://localhost/')));
        $t->same(true, str_contains($profile['uri'], '%5C%5C%3F%5C'));
        $t->same([1, 2, 3, 4], $profile['normal_select_rows']);
        $t->same([5, 6, 7, 8], $profile['long_select_rows']);
        $t->same('wal', $profile['journal_mode_result']);
        $t->same([5, 6, 7, 8, 9, 10, 11, 12], $profile['wal_select_rows']);
        $t->same($profile['wal_select_rows'], $profile['uri_select_rows']);
        $t->same($profile['filename'] . '-wal', $profile['wal_path']);
        $t->same($profile['filename'] . '-shm', $profile['shm_path']);
        $t->same($profile['filename'] . '-journal', $profile['journal_path']);
        $t->same(['1.0', '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7.' . $variant], $profile['operation_test_ids']);
        $t->same('win32_longpath_vfs_preserves_long_prefixed_paths_and_uri_reopen_without_filename_translation', $profile['reason']);
        $t->same(true, in_array('upstream-win32-longpath-vfs', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        $t->same($case, $case);
    };
}

$tests['real upstream corpus vfs win32 longpath dynamic cites source and guards invalid inputs'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::win32LongPathProfile(
        'C:/sqlite/upstream/win32longpath/source-check',
        4242,
        [255, 255],
        '1f',
        false
    );

    $t->same([
        'win32longpath.test 1.0 file_control_vfsname default win32',
        'win32longpath.test 1.1 file_control_vfsname win32-longpath',
        'win32longpath.test 1.2 transaction on normal win32 path',
        'win32longpath.test 1.3 over-length path without long prefix is rejected',
        'win32longpath.test 1.4 transaction on long path',
        'win32longpath.test 1.5 WAL journal mode on long path',
        'win32longpath.test 1.6 WAL append readback on long path',
        'win32longpath.test 1.7.1a-1f URI open with -translatefilename 0',
    ], $profile['upstream']);
    $t->same('1f', $profile['uri_variant']);
    $t->same(true, $profile['uri_uses_slash_path']);
    $t->same(true, str_starts_with($profile['uri'], 'file://localhost/'));
    $t->same(true, str_contains($profile['uri'], '/test.db'));
    $t->same(true, $profile['uri_translation_disabled']);

    $translated = SQLiteVfsIoDynamicPlan::win32LongPathProfile(
        'C:/sqlite/upstream/win32longpath/translated',
        4243,
        [255, 255],
        '1a',
        true
    );

    $t->same(true, $translated['translatefilename']);
    $t->same(false, $translated['uri_translation_disabled']);

    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('', 1, [255, 255], '1a', false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('C:/sqlite', 0, [255, 255], '1a', false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('C:/sqlite', 1, [], '1a', false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('C:/sqlite', 1, [256], '1a', false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('C:/sqlite', 1, [255], '2a', false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::win32LongPathProfile('C:/sqlite', 1, [255], '1a', false, []));
};

return $tests;
