<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteTenantSavepointWalPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = static fn (string $tenant): string => $page("{$tenant}-page-1-base") . $page("{$tenant}-page-2-base") . $page("{$tenant}-page-3-base");

$makeWal = static function (array $frames, int $checkpoint = 46) use ($pageSize, $page): string {
    $salt1 = 0x46464646;
    $salt2 = 0x57575757;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeTenant = static function (int $tenantId, string $name, array $walFrames, callable $stackFactory, array $pages) use ($database, $makeWal, $pageSize): array {
    $walBytes = $makeWal($walFrames, 40 + $tenantId);

    return [
        'tenant_id' => $tenantId,
        'database_path' => $tenantId === 1 ? '/tmp/app-main.sqlite' : "/tmp/app-tenant-{$tenantId}.sqlite",
        'database_bytes' => $database($name),
        'wal' => SQLiteWal::parse($walBytes, $pageSize, true),
        'wal_bytes' => $walBytes,
        'savepoints' => $stackFactory($name),
        'savepoint' => 'settings_import',
        'page_numbers' => $pages,
    ];
};

$tenantOneStack = static function (string $name) use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('tenant_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('settings_import');
    $stack->recordPageImageWrite(2, $page("{$name}-page-2-base"));
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('nested_cleanup');
    $stack->recordPageImageWrite(3, $page("{$name}-page-3-base"));
    $stack->recordWalFrameWrite(5, 3);

    return $stack;
};

$tenantTwoStack = static function (string $name) use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('tenant_import');
    $stack->recordWalFrameWrite(1, 1, true);
    $stack->savepoint('settings_import');
    $stack->recordPageImageWrite(1, $page("{$name}-page-1-base"));
    $stack->recordPageImageWrite(3, $page("{$name}-page-3-base"));
    $stack->recordWalFrameWrite(2, 1);
    $stack->recordWalFrameWrite(3, 3, true);

    return $stack;
};

$tenantThreeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('tenant_import');
    $stack->recordWalFrameWrite(1, 2, true);
    $stack->savepoint('settings_import');

    return $stack;
};

$plan = static fn (): array => SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([
    $makeTenant(1, 'main', [
        [1, 0, 'main-network-schema-commit'],
        [2, 3, 'main-settings-before-plugin'],
        [2, 0, 'main-plugin-settings-draft'],
        [3, 3, 'main-plugin-cache-commit'],
        [3, 0, 'main-nested-cleanup-draft'],
    ], $tenantOneStack, [1, 2, 3]),
    $makeTenant(2, 'tenant2', [
        [1, 3, 'tenant2-settings-before-plugin'],
        [1, 0, 'tenant2-plugin-settings-draft'],
        [3, 3, 'tenant2-plugin-cache-commit'],
    ], $tenantTwoStack, [1, 2, 3]),
    $makeTenant(3, 'tenant3', [
        [2, 3, 'tenant3-settings-stable-commit'],
    ], $tenantThreeStack, [1, 2, 3]),
], $pageSize);

$tenant = static function (int $tenantId) use ($plan): array {
    foreach ($plan()['tenants'] as $tenant) {
        if ($tenant['tenant_id'] === $tenantId) {
            return $tenant;
        }
    }
    throw new RuntimeException("Missing tenant {$tenantId}");
};

$cases = [
    'overall status records rollback' => static fn (): mixed => $plan()['status'],
    'tenant count includes main and two tenants' => static fn (): mixed => $plan()['tenant_count'],
    'rolled back tenant count excludes stable tenant' => static fn (): mixed => $plan()['rolled_back_tenant_count'],
    'stable tenant count records untouched savepoint' => static fn (): mixed => $plan()['stable_tenant_count'],
    'total restored pages spans affected tenants' => static fn (): mixed => $plan()['total_restored_pages'],
    'total discarded wal frames spans affected tenants' => static fn (): mixed => $plan()['total_discarded_wal_frames'],
    'dependencies include tenant marker' => static fn (): mixed => in_array('sqlite-application-tenant-savepoint-wal-current-next', $plan()['dependencies'], true),
    'dependencies include tenant entry marker' => static fn (): mixed => in_array('sqlite-application-tenant-entry-savepoint-wal', $plan()['dependencies'], true),
    'dependencies include savepoint recovery marker' => static fn (): mixed => in_array('sqlite-wal-savepoint-recovery-current-next', $plan()['dependencies'], true),
    'current reader matrix keys preserve tenant ids' => static fn (): mixed => array_keys($plan()['current_reader_matrix']),
    'next reader matrix keys preserve tenant ids' => static fn (): mixed => array_keys($plan()['next_reader_matrix']),
    'main database path preserved' => static fn (): mixed => $tenant(1)['database_path'],
    'main tenant marked rolled back' => static fn (): mixed => $tenant(1)['rolled_back'],
    'main restores plugin and nested page images' => static fn (): mixed => $tenant(1)['restored_page_numbers'],
    'main missing image includes nested WAL-only page' => static fn (): mixed => $tenant(1)['missing_page_numbers'],
    'main rollback frame keeps prior commit' => static fn (): mixed => $tenant(1)['rollback_to_frame'],
    'main retained wal frame count' => static fn (): mixed => $tenant(1)['retained_wal_frame_count'],
    'main discarded wal frame count' => static fn (): mixed => $tenant(1)['discarded_wal_frame_count'],
    'main current wal bytes are retained prefix' => static fn (): mixed => $tenant(1)['current_wal_bytes_length'],
    'main current sources show retained wal and restored database' => static fn (): mixed => $tenant(1)['current_reader_sources'],
    'main next sources match current after rollback' => static fn (): mixed => $tenant(1)['next_reader_sources'],
    'main current frame indexes stop before plugin frames' => static fn (): mixed => $tenant(1)['current_reader_frame_indexes'],
    'main next frame indexes stop before plugin frames' => static fn (): mixed => $tenant(1)['next_reader_frame_indexes'],
    'main current reader has no errors' => static fn (): mixed => $tenant(1)['current_reader_errors'],
    'main next reader has no errors' => static fn (): mixed => $tenant(1)['next_reader_errors'],
    'main current next images match' => static fn (): mixed => $tenant(1)['images_match'],
    'main can checkpoint retained prefix' => static fn (): mixed => $tenant(1)['can_checkpoint'],
    'main checkpoint page count preserved' => static fn (): mixed => $tenant(1)['checkpoint_database_page_count'],
    'tenant two path preserved' => static fn (): mixed => $tenant(2)['database_path'],
    'tenant two marked rolled back' => static fn (): mixed => $tenant(2)['rolled_back'],
    'tenant two restores two pages' => static fn (): mixed => $tenant(2)['restored_page_numbers'],
    'tenant two has no missing page images' => static fn (): mixed => $tenant(2)['missing_page_numbers'],
    'tenant two rollback frame keeps first commit' => static fn (): mixed => $tenant(2)['rollback_to_frame'],
    'tenant two discarded wal frame count' => static fn (): mixed => $tenant(2)['discarded_wal_frame_count'],
    'tenant two current wal bytes are one frame prefix' => static fn (): mixed => $tenant(2)['current_wal_bytes_length'],
    'tenant two current sources use wal then database' => static fn (): mixed => $tenant(2)['current_reader_sources'],
    'tenant two frame indexes drop plugin frames' => static fn (): mixed => $tenant(2)['current_reader_frame_indexes'],
    'tenant two images match after recovery' => static fn (): mixed => $tenant(2)['images_match'],
    'tenant two can checkpoint retained prefix' => static fn (): mixed => $tenant(2)['can_checkpoint'],
    'tenant two checkpoint page count preserved' => static fn (): mixed => $tenant(2)['checkpoint_database_page_count'],
    'tenant three path preserved' => static fn (): mixed => $tenant(3)['database_path'],
    'tenant three remains stable' => static fn (): mixed => $tenant(3)['rolled_back'],
    'tenant three restores no pages' => static fn (): mixed => $tenant(3)['restored_page_numbers'],
    'tenant three discards no wal frames' => static fn (): mixed => $tenant(3)['discarded_wal_frame_count'],
    'tenant three rollback frame is after stable commit' => static fn (): mixed => $tenant(3)['rollback_to_frame'],
    'tenant three current sources preserve stable wal' => static fn (): mixed => $tenant(3)['current_reader_sources'],
    'tenant three frame indexes preserve stable commit' => static fn (): mixed => $tenant(3)['current_reader_frame_indexes'],
    'tenant three images match' => static fn (): mixed => $tenant(3)['images_match'],
    'tenant three can checkpoint stable prefix' => static fn (): mixed => $tenant(3)['can_checkpoint'],
    'current matrix main sources' => static fn (): mixed => $plan()['current_reader_matrix'][1],
    'next matrix tenant two sources' => static fn (): mixed => $plan()['next_reader_matrix'][2],
    'empty tenant list is rejected' => static function () use ($pageSize): mixed {
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'zero page size is rejected' => static function () use ($makeTenant, $tenantThreeStack): mixed {
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1])], 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing tenant id is rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1]);
        unset($tenant['tenant_id']);
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'empty database path is rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1]);
        $tenant['database_path'] = '';
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'misaligned database bytes are rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1]);
        $tenant['database_bytes'] .= 'x';
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'empty page list is rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, []);
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing savepoint is rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1]);
        $tenant['savepoint'] = 'missing';
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'mismatched wal bytes are rejected' => static function () use ($makeTenant, $tenantThreeStack, $pageSize): mixed {
        $tenant = $makeTenant(3, 'tenant3', [[2, 3, 'tenant3-settings-stable-commit']], $tenantThreeStack, [1]);
        $tenant['wal_bytes'] = substr($tenant['wal_bytes'], 0, -1) . 'x';
        try {
            SQLiteTenantSavepointWalPlan::rollbackToAcrossTenants([$tenant], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'overall status records rollback' => 'rolled_back',
    'tenant count includes main and two tenants' => 3,
    'rolled back tenant count excludes stable tenant' => 2,
    'stable tenant count records untouched savepoint' => 1,
    'total restored pages spans affected tenants' => 4,
    'total discarded wal frames spans affected tenants' => 5,
    'dependencies include tenant marker' => true,
    'dependencies include tenant entry marker' => true,
    'dependencies include savepoint recovery marker' => true,
    'current reader matrix keys preserve tenant ids' => [1, 2, 3],
    'next reader matrix keys preserve tenant ids' => [1, 2, 3],
    'main database path preserved' => '/tmp/app-main.sqlite',
    'main tenant marked rolled back' => true,
    'main restores plugin and nested page images' => [2, 3],
    'main missing image includes nested WAL-only page' => [],
    'main rollback frame keeps prior commit' => 2,
    'main retained wal frame count' => 2,
    'main discarded wal frame count' => 3,
    'main current wal bytes are retained prefix' => 1104,
    'main current sources show retained wal and restored database' => ['wal', 'wal', 'database'],
    'main next sources match current after rollback' => ['wal', 'wal', 'database'],
    'main current frame indexes stop before plugin frames' => [1, 2, null],
    'main next frame indexes stop before plugin frames' => [1, 2, null],
    'main current reader has no errors' => [],
    'main next reader has no errors' => [],
    'main current next images match' => true,
    'main can checkpoint retained prefix' => true,
    'main checkpoint page count preserved' => 3,
    'tenant two path preserved' => '/tmp/app-tenant-2.sqlite',
    'tenant two marked rolled back' => true,
    'tenant two restores two pages' => [1, 3],
    'tenant two has no missing page images' => [],
    'tenant two rollback frame keeps first commit' => 1,
    'tenant two discarded wal frame count' => 2,
    'tenant two current wal bytes are one frame prefix' => 568,
    'tenant two current sources use wal then database' => ['wal', 'database', 'database'],
    'tenant two frame indexes drop plugin frames' => [1, null, null],
    'tenant two images match after recovery' => true,
    'tenant two can checkpoint retained prefix' => true,
    'tenant two checkpoint page count preserved' => 3,
    'tenant three path preserved' => '/tmp/app-tenant-3.sqlite',
    'tenant three remains stable' => false,
    'tenant three restores no pages' => [],
    'tenant three discards no wal frames' => 0,
    'tenant three rollback frame is after stable commit' => 1,
    'tenant three current sources preserve stable wal' => ['database', 'wal', 'database'],
    'tenant three frame indexes preserve stable commit' => [null, 1, null],
    'tenant three images match' => true,
    'tenant three can checkpoint stable prefix' => true,
    'current matrix main sources' => ['wal', 'wal', 'database'],
    'next matrix tenant two sources' => ['wal', 'database', 'database'],
    'empty tenant list is rejected' => 'rejected',
    'zero page size is rejected' => 'rejected',
    'missing tenant id is rejected' => 'rejected',
    'empty database path is rejected' => 'rejected',
    'misaligned database bytes are rejected' => 'rejected',
    'empty page list is rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
    'mismatched wal bytes are rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['application tenant savepoint wal current next46 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
