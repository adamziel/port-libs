<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteMultisiteSavepointWalPlan;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = static fn (string $site): string => $page("{$site}-page-1-base") . $page("{$site}-page-2-base") . $page("{$site}-page-3-base");

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

$makeSite = static function (int $blogId, string $name, array $walFrames, callable $stackFactory, array $pages) use ($database, $makeWal, $pageSize): array {
    $walBytes = $makeWal($walFrames, 40 + $blogId);

    return [
        'blog_id' => $blogId,
        'database_path' => $blogId === 1 ? 'wp-content/database/main.sqlite' : "wp-content/database/site-{$blogId}.sqlite",
        'database_bytes' => $database($name),
        'wal' => SQLiteWal::parse($walBytes, $pageSize, true),
        'wal_bytes' => $walBytes,
        'savepoints' => $stackFactory($name),
        'savepoint' => 'plugin_import',
        'page_numbers' => $pages,
    ];
};

$siteOneStack = static function (string $name) use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('network_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_import');
    $stack->recordPageImageWrite(2, $page("{$name}-page-2-base"));
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 3, true);
    $stack->savepoint('nested_cleanup');
    $stack->recordPageImageWrite(3, $page("{$name}-page-3-base"));
    $stack->recordWalFrameWrite(5, 3);

    return $stack;
};

$siteTwoStack = static function (string $name) use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('network_import');
    $stack->recordWalFrameWrite(1, 1, true);
    $stack->savepoint('plugin_import');
    $stack->recordPageImageWrite(1, $page("{$name}-page-1-base"));
    $stack->recordPageImageWrite(3, $page("{$name}-page-3-base"));
    $stack->recordWalFrameWrite(2, 1);
    $stack->recordWalFrameWrite(3, 3, true);

    return $stack;
};

$siteThreeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('network_import');
    $stack->recordWalFrameWrite(1, 2, true);
    $stack->savepoint('plugin_import');

    return $stack;
};

$plan = static fn (): array => SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([
    $makeSite(1, 'main', [
        [1, 0, 'main-network-schema-commit'],
        [2, 3, 'main-options-before-plugin'],
        [2, 0, 'main-plugin-options-draft'],
        [3, 3, 'main-plugin-transient-commit'],
        [3, 0, 'main-nested-cleanup-draft'],
    ], $siteOneStack, [1, 2, 3]),
    $makeSite(2, 'site2', [
        [1, 3, 'site2-options-before-plugin'],
        [1, 0, 'site2-plugin-options-draft'],
        [3, 3, 'site2-plugin-cache-commit'],
    ], $siteTwoStack, [1, 2, 3]),
    $makeSite(3, 'site3', [
        [2, 3, 'site3-options-stable-commit'],
    ], $siteThreeStack, [1, 2, 3]),
], $pageSize);

$site = static function (int $blogId) use ($plan): array {
    foreach ($plan()['sites'] as $site) {
        if ($site['blog_id'] === $blogId) {
            return $site;
        }
    }
    throw new RuntimeException("Missing site {$blogId}");
};

$cases = [
    'overall status records rollback' => static fn (): mixed => $plan()['status'],
    'site count includes main and two blogs' => static fn (): mixed => $plan()['site_count'],
    'rolled back site count excludes stable site' => static fn (): mixed => $plan()['rolled_back_site_count'],
    'stable site count records untouched savepoint' => static fn (): mixed => $plan()['stable_site_count'],
    'total restored pages spans affected blogs' => static fn (): mixed => $plan()['total_restored_pages'],
    'total discarded wal frames spans affected blogs' => static fn (): mixed => $plan()['total_discarded_wal_frames'],
    'dependencies include multisite marker' => static fn (): mixed => in_array('sqlite-application-multisite-savepoint-wal-current-next', $plan()['dependencies'], true),
    'dependencies include site marker' => static fn (): mixed => in_array('sqlite-application-multisite-site-savepoint-wal', $plan()['dependencies'], true),
    'dependencies include savepoint recovery marker' => static fn (): mixed => in_array('sqlite-wal-savepoint-recovery-current-next', $plan()['dependencies'], true),
    'current reader matrix keys preserve blog ids' => static fn (): mixed => array_keys($plan()['current_reader_matrix']),
    'next reader matrix keys preserve blog ids' => static fn (): mixed => array_keys($plan()['next_reader_matrix']),
    'main database path preserved' => static fn (): mixed => $site(1)['database_path'],
    'main site marked rolled back' => static fn (): mixed => $site(1)['rolled_back'],
    'main restores plugin and nested page images' => static fn (): mixed => $site(1)['restored_page_numbers'],
    'main missing image includes nested wal only page' => static fn (): mixed => $site(1)['missing_page_numbers'],
    'main rollback frame keeps prior commit' => static fn (): mixed => $site(1)['rollback_to_frame'],
    'main retained wal frame count' => static fn (): mixed => $site(1)['retained_wal_frame_count'],
    'main discarded wal frame count' => static fn (): mixed => $site(1)['discarded_wal_frame_count'],
    'main current wal bytes are retained prefix' => static fn (): mixed => $site(1)['current_wal_bytes_length'],
    'main current sources show retained wal and restored database' => static fn (): mixed => $site(1)['current_reader_sources'],
    'main next sources match current after rollback' => static fn (): mixed => $site(1)['next_reader_sources'],
    'main current frame indexes stop before plugin frames' => static fn (): mixed => $site(1)['current_reader_frame_indexes'],
    'main next frame indexes stop before plugin frames' => static fn (): mixed => $site(1)['next_reader_frame_indexes'],
    'main current reader has no errors' => static fn (): mixed => $site(1)['current_reader_errors'],
    'main next reader has no errors' => static fn (): mixed => $site(1)['next_reader_errors'],
    'main current next images match' => static fn (): mixed => $site(1)['images_match'],
    'main can checkpoint retained prefix' => static fn (): mixed => $site(1)['can_checkpoint'],
    'main checkpoint page count preserved' => static fn (): mixed => $site(1)['checkpoint_database_page_count'],
    'site two path preserved' => static fn (): mixed => $site(2)['database_path'],
    'site two marked rolled back' => static fn (): mixed => $site(2)['rolled_back'],
    'site two restores two pages' => static fn (): mixed => $site(2)['restored_page_numbers'],
    'site two has no missing page images' => static fn (): mixed => $site(2)['missing_page_numbers'],
    'site two rollback frame keeps first commit' => static fn (): mixed => $site(2)['rollback_to_frame'],
    'site two discarded wal frame count' => static fn (): mixed => $site(2)['discarded_wal_frame_count'],
    'site two current wal bytes are one frame prefix' => static fn (): mixed => $site(2)['current_wal_bytes_length'],
    'site two current sources use wal then database' => static fn (): mixed => $site(2)['current_reader_sources'],
    'site two frame indexes drop plugin frames' => static fn (): mixed => $site(2)['current_reader_frame_indexes'],
    'site two images match after recovery' => static fn (): mixed => $site(2)['images_match'],
    'site two can checkpoint retained prefix' => static fn (): mixed => $site(2)['can_checkpoint'],
    'site two checkpoint page count preserved' => static fn (): mixed => $site(2)['checkpoint_database_page_count'],
    'site three path preserved' => static fn (): mixed => $site(3)['database_path'],
    'site three remains stable' => static fn (): mixed => $site(3)['rolled_back'],
    'site three restores no pages' => static fn (): mixed => $site(3)['restored_page_numbers'],
    'site three discards no wal frames' => static fn (): mixed => $site(3)['discarded_wal_frame_count'],
    'site three rollback frame is after stable commit' => static fn (): mixed => $site(3)['rollback_to_frame'],
    'site three current sources preserve stable wal' => static fn (): mixed => $site(3)['current_reader_sources'],
    'site three frame indexes preserve stable commit' => static fn (): mixed => $site(3)['current_reader_frame_indexes'],
    'site three images match' => static fn (): mixed => $site(3)['images_match'],
    'site three can checkpoint stable prefix' => static fn (): mixed => $site(3)['can_checkpoint'],
    'current matrix main sources' => static fn (): mixed => $plan()['current_reader_matrix'][1],
    'next matrix site two sources' => static fn (): mixed => $plan()['next_reader_matrix'][2],
    'empty site list is rejected' => static function () use ($pageSize): mixed {
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'zero page size is rejected' => static function () use ($makeSite, $siteThreeStack): mixed {
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1])], 0);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing blog id is rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1]);
        unset($site['blog_id']);
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'empty database path is rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1]);
        $site['database_path'] = '';
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'misaligned database bytes are rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1]);
        $site['database_bytes'] .= 'x';
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'empty page list is rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, []);
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing savepoint is rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1]);
        $site['savepoint'] = 'missing';
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'mismatched wal bytes are rejected' => static function () use ($makeSite, $siteThreeStack, $pageSize): mixed {
        $site = $makeSite(3, 'site3', [[2, 3, 'site3-options-stable-commit']], $siteThreeStack, [1]);
        $site['wal_bytes'] = substr($site['wal_bytes'], 0, -1) . 'x';
        try {
            SQLiteMultisiteSavepointWalPlan::rollbackToAcrossSites([$site], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
];

$expected = [
    'overall status records rollback' => 'rolled_back',
    'site count includes main and two blogs' => 3,
    'rolled back site count excludes stable site' => 2,
    'stable site count records untouched savepoint' => 1,
    'total restored pages spans affected blogs' => 4,
    'total discarded wal frames spans affected blogs' => 5,
    'dependencies include multisite marker' => true,
    'dependencies include site marker' => true,
    'dependencies include savepoint recovery marker' => true,
    'current reader matrix keys preserve blog ids' => [1, 2, 3],
    'next reader matrix keys preserve blog ids' => [1, 2, 3],
    'main database path preserved' => 'wp-content/database/main.sqlite',
    'main site marked rolled back' => true,
    'main restores plugin and nested page images' => [2, 3],
    'main missing image includes nested wal only page' => [],
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
    'site two path preserved' => 'wp-content/database/site-2.sqlite',
    'site two marked rolled back' => true,
    'site two restores two pages' => [1, 3],
    'site two has no missing page images' => [],
    'site two rollback frame keeps first commit' => 1,
    'site two discarded wal frame count' => 2,
    'site two current wal bytes are one frame prefix' => 568,
    'site two current sources use wal then database' => ['wal', 'database', 'database'],
    'site two frame indexes drop plugin frames' => [1, null, null],
    'site two images match after recovery' => true,
    'site two can checkpoint retained prefix' => true,
    'site two checkpoint page count preserved' => 3,
    'site three path preserved' => 'wp-content/database/site-3.sqlite',
    'site three remains stable' => false,
    'site three restores no pages' => [],
    'site three discards no wal frames' => 0,
    'site three rollback frame is after stable commit' => 1,
    'site three current sources preserve stable wal' => ['database', 'wal', 'database'],
    'site three frame indexes preserve stable commit' => [null, 1, null],
    'site three images match' => true,
    'site three can checkpoint stable prefix' => true,
    'current matrix main sources' => ['wal', 'wal', 'database'],
    'next matrix site two sources' => ['wal', 'database', 'database'],
    'empty site list is rejected' => 'rejected',
    'zero page size is rejected' => 'rejected',
    'missing blog id is rejected' => 'rejected',
    'empty database path is rejected' => 'rejected',
    'misaligned database bytes are rejected' => 'rejected',
    'empty page list is rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
    'mismatched wal bytes are rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['application multisite savepoint wal current next46 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
