<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeWal = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x12345678;
    $salt2 = 0x9abcdef0;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 101, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('import');
    $stack->recordPageImageWrite(1, $page('before-page-1'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->savepoint('plugin_a');
    $stack->recordPageImageWrite(2, $page('before-page-2'));
    $stack->recordWalFrameWrite(2, 2);
    $stack->savepoint('plugin_b');
    $stack->recordPageImageWrite(3, $page('before-page-3'));
    $stack->recordWalFrameWrite(3, 3, true);
    $stack->recordWalFrameWrite(4, 2);

    return $stack;
};

$walBytes = $makeWal([
    [1, 0, $page('wal-frame-1')],
    [2, 0, $page('wal-frame-2')],
    [3, 3, $page('wal-frame-3-commit')],
    [2, 0, $page('wal-frame-4-tail')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$corpusCases = [
    'savepoint rollback target frame is prior wal start' => static fn (): mixed => $makeStack()->walRollbackToPlan('plugin_a')['rollback_to_frame'],
    'savepoint rollback discards nested frame count' => static fn (): mixed => count($makeStack()->walRollbackToPlan('plugin_a')['discarded_wal_frames']),
    'savepoint rollback reports sorted dirty pages' => static fn (): mixed => $makeStack()->walRollbackToPlan('plugin_a')['discarded_page_numbers'],
    'savepoint rollback preserves transaction active state' => static fn (): mixed => $makeStack()->walRollbackToPlan('plugin_a')['transaction_active_after'],
    'nested savepoint rollback target frame includes outer writes' => static fn (): mixed => $makeStack()->walRollbackToPlan('plugin_b')['rollback_to_frame'],
    'nested savepoint rollback discards only nested frames' => static fn (): mixed => count($makeStack()->walRollbackToPlan('plugin_b')['discarded_wal_frames']),
    'byte truncation retains header plus outer frames' => static fn (): mixed => $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, $walBytes)['truncate_to_bytes'],
    'byte truncation marks needed for plugin rollback' => static fn (): mixed => $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, $walBytes)['needs_truncate'],
    'byte truncation reports original frame count' => static fn (): mixed => $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, $walBytes)['original_frame_count'],
    'byte truncation reports retained frame count' => static fn (): mixed => $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, $walBytes)['retained_frame_count'],
    'byte truncation reports discarded frame count' => static fn (): mixed => $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, $walBytes)['discarded_frame_count'],
    'wal byte image is truncated to retained prefix' => static fn (): mixed => strlen($makeStack()->walRollbackToWalBytes('plugin_a', $wal, $walBytes)),
    'wal byte image preserves exact prefix bytes' => static fn (): mixed => $makeStack()->walRollbackToWalBytes('plugin_a', $wal, $walBytes) === substr($walBytes, 0, 32 + (1 * (24 + $pageSize))),
    'rollback to savepoint clears target pending wal frames' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->walRollbackToWithPlan('plugin_a');
        return $stack->pendingWalFrameIndexes();
    },
    'rollback to savepoint leaves target frame open' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->walRollbackToWithPlan('plugin_a');
        return $stack->names();
    },
    'release merges nested wal frame indexes into parent' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('plugin_b');
        return $stack->pendingWalFrameIndexes();
    },
    'release outer savepoint preserves transaction wal frames' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->release('plugin_a');
        return $stack->pendingWalFrameIndexes();
    },
    'commit clears pending wal frame indexes' => static function () use ($makeStack): mixed {
        $stack = $makeStack();
        $stack->commit();
        return $stack->pendingWalFrameIndexes();
    },
    'rollback image plan restores savepoint pages' => static fn (): mixed => $makeStack()->rollbackToImagePlan('plugin_a', 512)['restored_page_numbers'],
    'rollback database image restores page content' => static function () use ($makeStack, $page): mixed {
        return substr($makeStack()->rollbackToDatabaseImage('plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512), 512, 13);
    },
    'vfs savepoint rollback applies database and wal operations' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/wp-content/database/.ht.sqlite', $wal, $walBytes);
        return $result['applied'];
    },
    'vfs savepoint rollback writes restored database image' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return substr((string) file_get_contents($root . '/db/wp.sqlite'), 512, 13);
    },
    'vfs savepoint rollback truncates wal file' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return filesize($root . '/db/wp.sqlite-wal');
    },
    'vfs savepoint rollback reports wal truncation count' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return $result['wal_truncation']['discarded_frame_count'];
    },
    'vfs savepoint rollback records durable syncs' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return $result['durable_syncs'];
    },
    'vfs savepoint rollback records directory sync' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return $result['directory_syncs'];
    },
    'vfs savepoint rollback carries pager dependencies' => static function () use ($makeStack, $wal, $walBytes, $page): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4));
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/db/wp.sqlite', $wal, $walBytes);
        return in_array('sqlite-savepoint-wal-rollback', $result['dependencies'], true);
    },
    'savepoint wal bytes mismatch is rejected' => static function () use ($makeStack, $wal, $walBytes): mixed {
        try {
            $makeStack()->walRollbackToByteTruncationPlan('plugin_a', $wal, substr($walBytes, 0, -1) . 'x');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'vfs savepoint rollback requires paired wal inputs' => static function () use ($makeStack, $page, $wal): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4))))
                ->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/wp.sqlite', $wal, null);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only writer rejects savepoint rollback apply' => static function () use ($makeStack, $page): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-pager-corpus-' . bin2hex(random_bytes(4)), true))
                ->applySavepointRollback($makeStack(), 'plugin_a', $page('dirty-1') . $page('dirty-2') . $page('dirty-3'), 512, '/wp.sqlite');
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'savepoint rollback target frame is prior wal start' => 1,
    'savepoint rollback discards nested frame count' => 3,
    'savepoint rollback reports sorted dirty pages' => [2, 3],
    'savepoint rollback preserves transaction active state' => true,
    'nested savepoint rollback target frame includes outer writes' => 2,
    'nested savepoint rollback discards only nested frames' => 2,
    'byte truncation retains header plus outer frames' => 568,
    'byte truncation marks needed for plugin rollback' => true,
    'byte truncation reports original frame count' => 4,
    'byte truncation reports retained frame count' => 1,
    'byte truncation reports discarded frame count' => 3,
    'wal byte image is truncated to retained prefix' => 568,
    'wal byte image preserves exact prefix bytes' => true,
    'rollback to savepoint clears target pending wal frames' => [1],
    'rollback to savepoint leaves target frame open' => ['import', 'plugin_a'],
    'release merges nested wal frame indexes into parent' => [1, 2, 3, 4],
    'release outer savepoint preserves transaction wal frames' => [1, 2, 3, 4],
    'commit clears pending wal frame indexes' => [],
    'rollback image plan restores savepoint pages' => [2, 3],
    'rollback database image restores page content' => 'before-page-2',
    'vfs savepoint rollback applies database and wal operations' => 7,
    'vfs savepoint rollback writes restored database image' => 'before-page-2',
    'vfs savepoint rollback truncates wal file' => 568,
    'vfs savepoint rollback reports wal truncation count' => 3,
    'vfs savepoint rollback records durable syncs' => 2,
    'vfs savepoint rollback records directory sync' => 1,
    'vfs savepoint rollback carries pager dependencies' => true,
    'savepoint wal bytes mismatch is rejected' => 'rejected',
    'vfs savepoint rollback requires paired wal inputs' => 'rejected',
    'read only writer rejects savepoint rollback apply' => 'rejected',
];

foreach ($corpusCases as $name => $callback) {
    $tests['pager wal savepoint corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
