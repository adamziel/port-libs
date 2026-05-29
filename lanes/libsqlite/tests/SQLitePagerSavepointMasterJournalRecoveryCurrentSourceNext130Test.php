<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$main = '/srv/wp/data/wp-next130.sqlite';
$aux = '/srv/wp/data/wp-next130-analytics.sqlite';
$master = '/srv/wp/data/wp-next130.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseImages = [
    $main => [
        1 => $page('next130 main stale header before master recovery'),
        2 => $page('next130 main stale wp_options root after crash'),
        3 => $page('next130 main stale active_plugins page after crash'),
        4 => $page('next130 main stale autoload index after crash'),
    ],
    $aux => [
        1 => $page('next130 aux stale header before master recovery'),
        2 => $page('next130 aux stale import audit root after crash'),
        3 => $page('next130 aux stale plugin audit leaf after crash'),
    ],
];
$recovered = [
    $main => [
        1 => $page('next130 main recovered header current source'),
        2 => $page('next130 main recovered wp_options root current source'),
        4 => $page('next130 main recovered autoload index current source'),
    ],
    $aux => [
        1 => $page('next130 aux recovered header current source'),
        2 => $page('next130 aux recovered import audit root current source'),
    ],
];
$savepointBefore = [
    $main => [
        2 => $recovered[$main][2],
        4 => $recovered[$main][4],
    ],
    $aux => [
        2 => $recovered[$aux][2],
    ],
];
$savepointWrites = [
    $main => [
        2 => $page('next130 main dirty wp_options root inside savepoint'),
        4 => $page('next130 main dirty autoload index inside savepoint'),
    ],
    $aux => [
        2 => $page('next130 aux dirty import audit root inside savepoint'),
    ],
];
$retryWrites = [
    $main => [
        2 => $page('next130 main retry wp_options root after rollback'),
        3 => $page('next130 main retry active_plugins after rollback'),
        5 => $page('next130 main retry overflow append after rollback'),
    ],
    $aux => [
        2 => $page('next130 aux retry import audit root after rollback'),
        4 => $page('next130 aux retry audit overflow after rollback'),
    ],
];
$cachedMembers = [
    $main . '-journal',
    '/srv/wp/data/old-detached.sqlite-journal',
];
$currentMembers = [
    $main . '-journal',
    $aux . '-journal',
];

$plan = static fn (
    ?int $size = null,
    ?string $masterPath = null,
    ?string $savepoint = null,
    ?array $cached = null,
    ?array $current = null,
    ?array $databases = null,
    ?array $recovery = null,
    ?array $before = null,
    ?array $writes = null,
    ?array $retry = null,
    bool $release = true,
): array => SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan::currentSourceNext(
    $size ?? $pageSize,
    $masterPath ?? $master,
    $savepoint ?? 'plugin-import-next130',
    $cached ?? $cachedMembers,
    $current ?? $currentMembers,
    $databases ?? $databaseImages,
    $recovery ?? $recovered,
    $before ?? $savepointBefore,
    $writes ?? $savepointWrites,
    $retry ?? $retryWrites,
    $release
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_savepoint_master_journal_recovery_current_source_next130'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_establishes_current_source_before_rollback_to_savepoint_retry'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'plugin-import-next130'],
    'cached members' => [static fn (): mixed => $plan()['cached_master_members'], $cachedMembers],
    'current members' => [static fn (): mixed => $plan()['current_master_members'], $currentMembers],
    'stale cached member' => [static fn (): mixed => $plan()['stale_cached_members'], ['/srv/wp/data/old-detached.sqlite-journal']],
    'new current member' => [static fn (): mixed => $plan()['new_current_members'], [$aux . '-journal']],
    'database paths' => [static fn (): mixed => $plan()['database_paths'], [$main, $aux]],
    'main recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'][$main], [1, 2, 4]],
    'aux recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'][$aux], [1, 2]],
    'main savepoint before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'][$main], [2, 4]],
    'aux savepoint before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'][$aux], [2]],
    'main savepoint write pages' => [static fn (): mixed => $plan()['savepoint_write_page_numbers'][$main], [2, 4]],
    'aux savepoint write pages' => [static fn (): mixed => $plan()['savepoint_write_page_numbers'][$aux], [2]],
    'main retry pages' => [static fn (): mixed => $plan()['retry_write_page_numbers'][$main], [2, 3, 5]],
    'aux retry pages' => [static fn (): mixed => $plan()['retry_write_page_numbers'][$aux], [2, 4]],
    'release main merged pages' => [static fn (): mixed => $plan()['release_merged_page_numbers'][$main], [2, 3, 5]],
    'release aux merged pages' => [static fn (): mixed => $plan()['release_merged_page_numbers'][$aux], [2, 4]],
    'recovered main header prefix' => [static fn (): mixed => $plan()['recovered_prefixes'][$main][1], 'next130 main recovered header current source'],
    'recovered main root prefix' => [static fn (): mixed => $plan()['recovered_prefixes'][$main][2], 'next130 main recovered wp_options root current source'],
    'recovered main untouched stale active plugins' => [static fn (): mixed => $plan()['recovered_prefixes'][$main][3], 'next130 main stale active_plugins page after crash'],
    'recovered aux root prefix' => [static fn (): mixed => $plan()['recovered_prefixes'][$aux][2], 'next130 aux recovered import audit root current source'],
    'dirty main root prefix' => [static fn (): mixed => $plan()['dirty_prefixes'][$main][2], 'next130 main dirty wp_options root inside savepoint'],
    'dirty main index prefix' => [static fn (): mixed => $plan()['dirty_prefixes'][$main][4], 'next130 main dirty autoload index inside savepoint'],
    'dirty aux root prefix' => [static fn (): mixed => $plan()['dirty_prefixes'][$aux][2], 'next130 aux dirty import audit root inside savepoint'],
    'rollback main root restored' => [static fn (): mixed => $plan()['rollback_prefixes'][$main][2], 'next130 main recovered wp_options root current source'],
    'rollback main index restored' => [static fn (): mixed => $plan()['rollback_prefixes'][$main][4], 'next130 main recovered autoload index current source'],
    'rollback aux root restored' => [static fn (): mixed => $plan()['rollback_prefixes'][$aux][2], 'next130 aux recovered import audit root current source'],
    'final main root retry' => [static fn (): mixed => $plan()['final_prefixes'][$main][2], 'next130 main retry wp_options root after rollback'],
    'final main active plugin retry' => [static fn (): mixed => $plan()['final_prefixes'][$main][3], 'next130 main retry active_plugins after rollback'],
    'final main index recovered' => [static fn (): mixed => $plan()['final_prefixes'][$main][4], 'next130 main recovered autoload index current source'],
    'final main overflow retry' => [static fn (): mixed => $plan()['final_prefixes'][$main][5], 'next130 main retry overflow append after rollback'],
    'final aux retry root' => [static fn (): mixed => $plan()['final_prefixes'][$aux][2], 'next130 aux retry import audit root after rollback'],
    'final aux stale leaf preserved' => [static fn (): mixed => $plan()['final_prefixes'][$aux][3], 'next130 aux stale plugin audit leaf after crash'],
    'final aux retry overflow' => [static fn (): mixed => $plan()['final_prefixes'][$aux][4], 'next130 aux retry audit overflow after rollback'],
    'source main page one recovered' => [static fn (): mixed => $plan()['final_sources'][$main][1], 'master-journal-recovered-current-source'],
    'source main page two retry' => [static fn (): mixed => $plan()['final_sources'][$main][2], 'retry-write-after-master-savepoint-recovery'],
    'source main page three retry' => [static fn (): mixed => $plan()['final_sources'][$main][3], 'retry-write-after-master-savepoint-recovery'],
    'source main page four rollback' => [static fn (): mixed => $plan()['final_sources'][$main][4], 'rollback-to-savepoint-master-recovered-before-image'],
    'source main page five retry' => [static fn (): mixed => $plan()['final_sources'][$main][5], 'retry-write-after-master-savepoint-recovery'],
    'source aux page one recovered' => [static fn (): mixed => $plan()['final_sources'][$aux][1], 'master-journal-recovered-current-source'],
    'source aux page two retry' => [static fn (): mixed => $plan()['final_sources'][$aux][2], 'retry-write-after-master-savepoint-recovery'],
    'source aux page three stale' => [static fn (): mixed => $plan()['final_sources'][$aux][3], 'stale-database-before-master-recovery'],
    'source aux page four retry' => [static fn (): mixed => $plan()['final_sources'][$aux][4], 'retry-write-after-master-savepoint-recovery'],
    'recovered bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['recovered_database_bytes'][$main], 'next130 main stale wp_options root after crash'), false],
    'recovered bytes keep stale active plugins' => [static fn (): mixed => str_contains($plan()['recovered_database_bytes'][$main], 'next130 main stale active_plugins page after crash'), true],
    'dirty bytes include dirty root' => [static fn (): mixed => str_contains($plan()['dirty_database_bytes'][$main], 'next130 main dirty wp_options root inside savepoint'), true],
    'dirty bytes include aux dirty root' => [static fn (): mixed => str_contains($plan()['dirty_database_bytes'][$aux], 'next130 aux dirty import audit root inside savepoint'), true],
    'rollback bytes exclude dirty root' => [static fn (): mixed => str_contains($plan()['rollback_database_bytes'][$main], 'next130 main dirty wp_options root inside savepoint'), false],
    'rollback bytes restore recovered root' => [static fn (): mixed => str_contains($plan()['rollback_database_bytes'][$main], 'next130 main recovered wp_options root current source'), true],
    'rollback bytes exclude aux dirty root' => [static fn (): mixed => str_contains($plan()['rollback_database_bytes'][$aux], 'next130 aux dirty import audit root inside savepoint'), false],
    'final bytes include main retry' => [static fn (): mixed => str_contains($plan()['final_database_bytes'][$main], 'next130 main retry active_plugins after rollback'), true],
    'final bytes include aux retry' => [static fn (): mixed => str_contains($plan()['final_database_bytes'][$aux], 'next130 aux retry audit overflow after rollback'), true],
    'final bytes exclude dirty root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'][$main], 'next130 main dirty wp_options root inside savepoint'), false],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 27],
    'operation reads current master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_members'],
    'operation discards stale cached member' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members'],
    'operation restores main page one' => [static fn (): mixed => $plan()['operations'][2]['reason'], 'recover_current_source_before_savepoint_rollback'],
    'operation records main before image' => [static fn (): mixed => $plan()['operations'][7]['reason'], 'savepoint_before_image_uses_master_recovered_current_source'],
    'operation writes savepoint page' => [static fn (): mixed => $plan()['operations'][10]['op'], 'write_savepoint_page'],
    'operation rollback restores main root' => [static fn (): mixed => $plan()['operations'][13]['op'], 'rollback_to_savepoint_before_image'],
    'operation retry capture main root' => [static fn (): mixed => $plan()['operations'][16]['op'], 'capture_retry_before_image'],
    'operation retry write main root' => [static fn (): mixed => $plan()['operations'][17]['op'], 'write_retry_page'],
    'operation retry capture aux overflow' => [static fn (): mixed => $plan()['operations'][24]['page_number'], 4],
    'operation release' => [static fn (): mixed => $plan()['operations'][26]['op'], 'release_savepoint_after_retry'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-master-journal-recovery-current-source-next130', $plan()['dependencies'], true), true],
    'dependency rollback source' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-restores-master-recovered-images', $plan()['dependencies'], true), true],
    'dependency retry current source' => [static fn (): mixed => in_array('sqlite-retry-captures-post-rollback-current-source', $plan()['dependencies'], true), true],
    'no release operations' => [static fn (): mixed => count($plan(release: false)['operations']), 26],
    'no release merged pages' => [static fn (): mixed => $plan(release: false)['release_merged_page_numbers'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint master journal recovery current source next130 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad page size rejected' => static fn () => $plan(size: 500),
    'empty master path rejected' => static fn () => $plan(masterPath: ''),
    'empty savepoint rejected' => static fn () => $plan(savepoint: ''),
    'empty current members rejected' => static fn () => $plan(current: []),
    'empty database images rejected' => static fn () => $plan(databases: []),
    'empty recovered pages rejected' => static fn () => $plan(recovery: []),
    'empty savepoint before rejected' => static fn () => $plan(before: []),
    'empty savepoint writes rejected' => static fn () => $plan(writes: []),
    'empty retry writes rejected' => static fn () => $plan(retry: []),
    'bad cached member rejected' => static fn () => $plan(cached: ['']),
    'missing current member rejected' => static fn () => $plan(current: [$main . '-journal']),
    'empty database path rejected' => static fn () => $plan(databases: ['' => [1 => $page('bad')]]),
    'zero database page rejected' => static fn () => $plan(databases: [$main => [0 => $page('bad')]]),
    'short recovered page rejected' => static fn () => $plan(recovery: [$main => [1 => 'short']]),
    'recovered outside database rejected' => static fn () => $plan(recovery: [$main => [8 => $page('outside')], $aux => $recovered[$aux]]),
    'savepoint database not open rejected' => static fn () => $plan(before: ['/tmp/missing.sqlite' => [1 => $page('missing')]]),
    'savepoint before must match recovered source' => static fn () => $plan(before: [$main => [2 => $databaseImages[$main][2]], $aux => $savepointBefore[$aux]]),
    'write database not open rejected' => static fn () => $plan(writes: ['/tmp/missing.sqlite' => [1 => $page('missing')]]),
    'write needs before image rejected' => static fn () => $plan(writes: [$main => [3 => $page('missing before')], $aux => $savepointWrites[$aux]]),
    'retry database not open rejected' => static fn () => $plan(retry: ['/tmp/missing.sqlite' => [1 => $page('missing')]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint master journal recovery current source next130 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
