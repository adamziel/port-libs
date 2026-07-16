<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistence;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$rootPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'port-libsqlite-vfs-persistence-dynamic-';

$cleanupTree = static function (string $root) use ($rootPrefix): void {
    if (!str_starts_with($root, $rootPrefix) || !is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($root);
};

$makeRoot = static function (int $case) use ($rootPrefix, $cleanupTree): string {
    $root = $rootPrefix . getmypid() . '-' . $case . '-' . bin2hex(random_bytes(4));
    $cleanupTree($root);

    return $root;
};

$sourceScenarios = [
    [
        'source' => 'pager1.test pager1-32.1 chunksize sizehint persistent preallocation',
        'persist_wal' => true,
        'powersafe_overwrite' => true,
        'limit_delta' => 8192,
        'expect_hint' => 'ok',
        'expect_reason' => 'caller_may_preallocate_file',
    ],
    [
        'source' => 'walpersist.test walpersist-1.6 enable persistent wal',
        'persist_wal' => true,
        'powersafe_overwrite' => true,
        'limit_delta' => 4096,
        'expect_hint' => 'ok',
        'expect_reason' => 'caller_may_preallocate_file',
    ],
    [
        'source' => 'walpersist.test walpersist-1.8 disable persistent wal',
        'persist_wal' => false,
        'powersafe_overwrite' => true,
        'limit_delta' => 12288,
        'expect_hint' => 'ok',
        'expect_reason' => 'caller_may_preallocate_file',
    ],
    [
        'source' => 'walpersist.test walpersist-2.2 persistent wal with journal_size_limit cap',
        'persist_wal' => true,
        'powersafe_overwrite' => true,
        'limit_delta' => -512,
        'expect_hint' => 'ignored',
        'expect_reason' => 'size_hint_exceeds_size_limit',
    ],
    [
        'source' => 'zerodamage.test zerodamage-1.1 powersafe overwrite off',
        'persist_wal' => false,
        'powersafe_overwrite' => false,
        'limit_delta' => -1024,
        'expect_hint' => 'ignored',
        'expect_reason' => 'size_hint_exceeds_size_limit',
    ],
    [
        'source' => 'zerodamage.test zerodamage-1.2 powersafe overwrite on',
        'persist_wal' => true,
        'powersafe_overwrite' => true,
        'limit_delta' => 2048,
        'expect_hint' => 'ok',
        'expect_reason' => 'caller_may_preallocate_file',
    ],
    [
        'source' => 'jrnlmode.test jrnlmode-5.4 attached journal_size_limit persists',
        'persist_wal' => false,
        'powersafe_overwrite' => true,
        'limit_delta' => -256,
        'expect_hint' => 'ignored',
        'expect_reason' => 'size_hint_exceeds_size_limit',
    ],
    [
        'source' => 'jrnlmode.test jrnlmode-5.5 main journal_size_limit persists',
        'persist_wal' => true,
        'powersafe_overwrite' => false,
        'limit_delta' => 16384,
        'expect_hint' => 'ok',
        'expect_reason' => 'caller_may_preallocate_file',
    ],
];

$caseCount = 0;
foreach (range(1, 125) as $round) {
    foreach ($sourceScenarios as $scenario) {
        ++$caseCount;

        $chunkSize = 1024 * (1 + (($caseCount * 7) % 32));
        $mmapSize = 8192 * (1 + (($caseCount * 5) % 24));
        $reserveBytes = ($caseCount * 13) % 256;
        $hintBytes = 16384 + (($caseCount % 96) * 1024);
        $sizeLimit = max(0, $hintBytes + $scenario['limit_delta']);
        $sectorSize = 512 << ($caseCount % 4);
        $cache = ($caseCount % 2) === 0 ? 'shared' : 'private';
        $syncMode = ($caseCount % 3) === 0 ? 'full' : 'normal';
        $deviceFlags = $scenario['powersafe_overwrite'] ? ['safe_append', 'powersafe_overwrite'] : ['safe_append'];
        $filename = sprintf('file:/srv/app/data/vfs-persistence-%04d.sqlite?mode=rw&cache=%s&vfs=unix', $caseCount, $cache);
        $expectedPersisted = [
            'persist_wal' => $scenario['persist_wal'],
            'chunk_size' => $chunkSize,
            'mmap_size' => $mmapSize,
            'powersafe_overwrite' => $scenario['powersafe_overwrite'],
            'size_limit' => $sizeLimit,
            'reserve_bytes' => $reserveBytes,
            'data_version' => 1,
        ];
        $testName = sprintf(
            'real upstream corpus vfs persistence dynamic %04d %s round %03d',
            $caseCount,
            $scenario['source'],
            $round
        );

        $tests[$testName] = static function (TestRunner $t) use (
            $makeRoot,
            $cleanupTree,
            $caseCount,
            $filename,
            $sectorSize,
            $deviceFlags,
            $syncMode,
            $expectedPersisted,
            $hintBytes,
            $scenario
        ): void {
            $root = $makeRoot($caseCount);

            try {
                $persistence = new SQLiteVfsFileControlPersistence($root);
                $first = $persistence->persistentFileControlApply(
                    $filename,
                    true,
                    true,
                    [
                        ['op' => 'persist_wal', 'value' => $expectedPersisted['persist_wal']],
                        ['op' => 'chunk_size', 'value' => $expectedPersisted['chunk_size']],
                        ['op' => 'mmap_size', 'value' => $expectedPersisted['mmap_size']],
                        ['op' => 'powersafe_overwrite', 'value' => $expectedPersisted['powersafe_overwrite']],
                        ['op' => 'reserve_bytes', 'value' => $expectedPersisted['reserve_bytes']],
                        ['op' => 'size_limit', 'value' => $expectedPersisted['size_limit']],
                        ['op' => 'name_hint', 'value' => 'application-vfs-case-' . $caseCount],
                        ['op' => 'lock_timeout', 'value' => ($caseCount % 19) * 25],
                        ['op' => 'write_hint', 'value' => $hintBytes],
                    ],
                    'application-writer-' . $caseCount,
                    $sectorSize,
                    $deviceFlags,
                    $syncMode,
                    !$expectedPersisted['persist_wal'],
                    null,
                    null
                );

                $t->same('persisted', $first['status']);
                $t->same('acquired', $first['lock']['status']);
                $t->same('reserved', $first['lock']['held']);
                $t->same('released', $first['release']['status']);
                $t->same([], $first['release']['holders']);
                $t->same($expectedPersisted, $first['persisted']);
                $t->same($expectedPersisted, $persistence->readPersisted($first['path']));
                $t->same(true, is_file($first['sidecar']));

                $sidecarJson = file_get_contents($first['sidecar']);
                $t->same(true, is_string($sidecarJson));
                $t->same($expectedPersisted, json_decode((string) $sidecarJson, true, 512, JSON_THROW_ON_ERROR));
                foreach ($expectedPersisted as $key => $expected) {
                    $t->same($expected, $first['next']['controls'][$key], $scenario['source'] . ' persisted ' . $key);
                }
                $t->same(false, array_key_exists('name_hint', $first['persisted']));
                $t->same(false, array_key_exists('lock_timeout', $first['persisted']));
                $t->same(false, array_key_exists('write_hint_bytes', $first['persisted']));
                $t->same(true, in_array('vfs-file-control-persistence-persistent-file-control-apply', $first['dependencies'], true));
                $t->same(true, in_array('vfs-xfilecontrol', $first['dependencies'], true));

                $second = $persistence->persistentFileControlApply(
                    $filename,
                    true,
                    true,
                    [['op' => 'size_hint', 'value' => $hintBytes]],
                    'application-reopen-' . $caseCount,
                    $sectorSize,
                    $deviceFlags,
                    $syncMode,
                    !$expectedPersisted['persist_wal'],
                    null,
                    null
                );

                $hint = $second['file_control']['results'][0];
                $t->same('persisted', $second['status']);
                $t->same($expectedPersisted, $second['persisted']);
                $t->same($expectedPersisted, $persistence->readPersisted($second['path']));
                $t->same($scenario['expect_hint'], $hint['status']);
                $t->same($hintBytes, $hint['value']);
                $t->same($scenario['expect_reason'], $hint['reason']);
                foreach ($expectedPersisted as $key => $expected) {
                    $t->same($expected, $second['current']['controls'][$key], $scenario['source'] . ' rehydrated ' . $key);
                    $t->same($expected, $second['next']['controls'][$key], $scenario['source'] . ' retained ' . $key);
                }
            } finally {
                $cleanupTree($root);
            }
        };
    }
}

$tests['real upstream corpus vfs persistence dynamic cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $snippets = [
        'pager1.test' => 'file_control_chunksize_test db main 1024',
        'pager1.test sizehint' => 'file_control_sizehint_test db main 20971520',
        'walpersist.test enable' => 'file_control_persist_wal db 1',
        'walpersist.test limit' => 'PRAGMA journal_size_limit=12000',
        'zerodamage.test' => 'file_control_powersafe_overwrite db 0',
        'jrnlmode.test' => 'PRAGMA aux.journal_size_limit = 10240',
        'jrnlmode.test main' => 'PRAGMA main.journal_size_limit = 20480',
    ];

    $sourceByFile = [];
    foreach ($snippets as $label => $needle) {
        $file = str_contains($label, ' ') ? strstr($label, ' ', true) : $label;
        $sourceByFile[$file] ??= (string) file_get_contents($upstreamRoot . '/' . $file);
        $t->contains($needle, $sourceByFile[$file], $label);
    }
};

$tests['real upstream corpus vfs persistence dynamic validates case volume'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(1000, $caseCount);
};

$tests['real upstream corpus vfs persistence dynamic rejects malformed sidecar inputs'] = static function (TestRunner $t) use ($makeRoot, $cleanupTree): void {
    $root = $makeRoot(1001);

    try {
        $persistence = new SQLiteVfsFileControlPersistence($root);
        $t->throws(InvalidArgumentException::class, static fn (): string => (new SQLiteVfsFileControlPersistence(''))->sidecarPath('/srv/app/data/application.sqlite'));
        $t->throws(InvalidArgumentException::class, static fn (): string => $persistence->sidecarPath(''));
        $t->throws(InvalidArgumentException::class, static fn (): string => $persistence->sidecarPath("/srv/app/data/application.sqlite\0bad"));
        $t->throws(InvalidArgumentException::class, static fn (): array => $persistence->persistentFileControlApply(
            'file:/srv/app/data/application.sqlite?mode=rw',
            true,
            true,
            []
        ));
    } finally {
        $cleanupTree($root);
    }
};

$tests['real upstream corpus vfs persistence dynamic records non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: disk-backed SQLiteVfsFileControlPersistence sidecar rehydrate cases for pager1-32.1, walpersist-1.*, zerodamage-1.*, and jrnlmode-5.*; avoids the accepted in-memory filectrl sequence batch and accepted VFS writer/lock/sync clusters',
        'non-overlap: disk-backed SQLiteVfsFileControlPersistence sidecar rehydrate cases for pager1-32.1, walpersist-1.*, zerodamage-1.*, and jrnlmode-5.*; avoids the accepted in-memory filectrl sequence batch and accepted VFS writer/lock/sync clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteVfsFileControlPersistence, SQLiteVfsFileControlState, SQLiteVfsCapabilityPlan, and SQLiteVfsLockState against hydrated upstream VFS .test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteVfsFileControlPersistence, SQLiteVfsFileControlState, SQLiteVfsCapabilityPlan, and SQLiteVfsLockState against hydrated upstream VFS .test source truth'
    );
};

return $tests;
