<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cache = [
    'active-select-options' => [
        'state' => 'active',
        'read_only' => true,
        'pages' => [
            1 => ['image' => $page('next104 active schema'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'database'],
            2 => ['image' => $page('next104 active options root'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'wal'],
        ],
    ],
    'read-retry-options' => [
        'state' => 'ready',
        'read_only' => true,
        'pages' => [
            2 => ['image' => $page('next104 stale options root'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'wal'],
            4 => ['image' => $page('next104 dirty statement page'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'statement', 'dirty' => true],
        ],
    ],
    'write-plugin-state' => [
        'state' => 'ready',
        'read_only' => false,
        'savepoint' => 'plugin-import',
        'pages' => [
            3 => ['image' => $page('next104 write active plugins'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'wal'],
            5 => ['image' => $page('next104 write autoload index'), 'source_id' => 'wal:10:journal:old', 'epoch' => 10, 'source' => 'database'],
        ],
    ],
    'stable-user-query' => [
        'state' => 'ready',
        'read_only' => true,
        'pages' => [
            7 => ['image' => $page('next104 stable usermeta'), 'source_id' => 'wal:11:journal:open', 'epoch' => 11, 'source' => 'database'],
        ],
    ],
];

$hot = [
    2 => $page('next104 recovered options root'),
    3 => $page('next104 recovered active plugins'),
];
$statementRollback = [
    4 => $page('next104 rollback transient stmt'),
    6 => $page('next104 rollback appended zero'),
];

$plan = static fn (
    array $cacheInput = null,
    array $hotInput = null,
    array $rollbackInput = null,
    array $retryReads = null,
    string $active = 'active-select-options',
    int $size = null,
    string $current = 'wal:11:journal:open',
    string $recovered = 'hot:12:journal:deleted',
    int $epoch = 11,
): array => SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan::plan(
    $size ?? $pageSize,
    $current,
    $recovered,
    $epoch,
    $cacheInput ?? $cache,
    $hotInput ?? $hot,
    $rollbackInput ?? $statementRollback,
    $retryReads ?? [2, 3, 4, 6, 7, 8],
    $active,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_statement_cache_current_source_next104'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovery_rekeys_statement_cache_by_current_source'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 64],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'wal:11:journal:open'],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 11],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], 'hot:12:journal:deleted'],
    'recovered epoch increments' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 12],
    'active statement name' => [static fn (): mixed => $plan()['active_statement'], 'active-select-options'],
    'statement count' => [static fn (): mixed => count($plan()['statements']), 4],
    'active pinned list' => [static fn (): mixed => $plan()['active_current_snapshot_statements'], ['active-select-options']],
    'expired statements' => [static fn (): mixed => $plan()['expired_statements'], ['read-retry-options', 'write-plugin-state']],
    'retryable read statements' => [static fn (): mixed => $plan()['retryable_read_statements'], ['read-retry-options']],
    'write statements blocked' => [static fn (): mixed => $plan()['write_statements_blocked_before_retry'], ['write-plugin-state']],
    'recovered page numbers merged' => [static fn (): mixed => $plan()['recovered_page_numbers'], [2, 3, 4, 6]],
    'active page numbers' => [static fn (): mixed => $plan()['statements'][0]['page_numbers'], [1, 2]],
    'active recovered page hit tracked' => [static fn (): mixed => $plan()['statements'][0]['recovered_page_numbers'], [2]],
    'active not expired while stepping' => [static fn (): mixed => $plan()['statements'][0]['requires_expire'], false],
    'active next action finishes current' => [static fn (): mixed => $plan()['statements'][0]['next_step_action'], 'finish_current_step_then_expire_on_reset'],
    'read retry page numbers' => [static fn (): mixed => $plan()['statements'][1]['page_numbers'], [2, 4]],
    'read retry recovered pages' => [static fn (): mixed => $plan()['statements'][1]['recovered_page_numbers'], [2, 4]],
    'read retry dirty pages' => [static fn (): mixed => $plan()['statements'][1]['dirty_page_numbers'], [4]],
    'read retry expires' => [static fn (): mixed => $plan()['statements'][1]['requires_expire'], true],
    'read retry action' => [static fn (): mixed => $plan()['statements'][1]['next_step_action'], 'sqlite_schema_then_reprepare_with_recovered_source'],
    'write statement savepoint' => [static fn (): mixed => $plan()['statements'][2]['savepoint'], 'plugin-import'],
    'write recovered page' => [static fn (): mixed => $plan()['statements'][2]['recovered_page_numbers'], [3]],
    'write stale pages' => [static fn (): mixed => $plan()['statements'][2]['stale_page_numbers'], [5]],
    'write action' => [static fn (): mixed => $plan()['statements'][2]['next_step_action'], 'sqlite_schema_before_write_retry'],
    'stable page numbers' => [static fn (): mixed => $plan()['statements'][3]['page_numbers'], [7]],
    'stable current only page' => [static fn (): mixed => $plan()['statements'][3]['current_only_page_numbers'], [7]],
    'stable action' => [static fn (): mixed => $plan()['statements'][3]['next_step_action'], 'reuse_prepared_statement_cache'],
    'retry read count' => [static fn (): mixed => count($plan()['retry_reads']), 6],
    'retry read page two seeded' => [static fn (): mixed => $plan()['retry_reads'][0]['cache_seeded'], true],
    'retry read page two source hot' => [static fn (): mixed => $plan()['retry_reads'][0]['source'], 'hot-journal-recovery'],
    'retry read page two prefix' => [static fn (): mixed => $plan()['retry_reads'][0]['image_prefix'], 'next104 recovered options root'],
    'retry read page three source hot' => [static fn (): mixed => $plan()['retry_reads'][1]['source'], 'hot-journal-recovery'],
    'retry read page four source rollback' => [static fn (): mixed => $plan()['retry_reads'][2]['source'], 'statement-rollback-before-image'],
    'retry read page six prefix' => [static fn (): mixed => $plan()['retry_reads'][3]['image_prefix'], 'next104 rollback appended zero'],
    'retry read page seven miss' => [static fn (): mixed => $plan()['retry_reads'][4]['cache_seeded'], false],
    'retry read page eight miss source' => [static fn (): mixed => $plan()['retry_reads'][5]['source'], 'pager-read-miss'],
    'retry read source id advanced' => [static fn (): mixed => $plan()['retry_reads'][3]['source_id'], 'hot:12:journal:deleted'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 4],
    'operation active pins' => [static fn (): mixed => $plan()['operations'][0]['op'], 'pin_active_statement_cache'],
    'operation active reason' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'current_step_keeps_pre_recovery_cache_until_reset'],
    'operation read expires' => [static fn (): mixed => $plan()['operations'][1]['op'], 'expire_statement_cache'],
    'operation write expires' => [static fn (): mixed => $plan()['operations'][2]['statement'], 'write-plugin-state'],
    'operation stable preserves' => [static fn (): mixed => $plan()['operations'][3]['op'], 'preserve_statement_cache'],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-statement-cache-current-source-next104', $plan()['dependencies'], true), true],
    'dependency hot journal' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $plan()['dependencies'], true), true],
    'dependency source token' => [static fn (): mixed => in_array('sqlite-statement-cache-source-token', $plan()['dependencies'], true), true],
    'dependency statement rollback' => [static fn (): mixed => in_array('sqlite-statement-journal-rollback-current-source', $plan()['dependencies'], true), true],
    'state active pins without explicit active name' => [static fn (): mixed => $plan(null, null, null, null, '')['active_current_snapshot_statements'], ['active-select-options']],
    'reset active expires when state ready' => [static function () use ($cache, $plan): mixed {
        $copy = $cache;
        $copy['active-select-options']['state'] = 'ready';
        return $plan($copy, null, null, null, '')['expired_statements'];
    }, ['active-select-options', 'read-retry-options', 'write-plugin-state']],
    'empty rollback pages still allowed' => [static fn (): mixed => $plan(null, null, [], [2])['recovered_page_numbers'], [2, 3]],
    'empty rollback retry misses unseeded page' => [static fn (): mixed => $plan(null, null, [], [4])['retry_reads'][0]['cache_seeded'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal statement cache current source next104 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad page size rejected' => static fn () => $plan(null, null, null, null, 'active-select-options', 0),
    'empty current source rejected' => static fn () => $plan(null, null, null, null, 'active-select-options', null, ''),
    'empty recovered source rejected' => static fn () => $plan(null, null, null, null, 'active-select-options', null, 'a', ''),
    'same source rejected' => static fn () => $plan(null, null, null, null, 'active-select-options', null, 'a', 'a'),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, 'active-select-options', null, 'a', 'b', 0),
    'empty cache rejected' => static fn () => $plan([]),
    'empty hot rejected' => static fn () => $plan(null, []),
    'empty retry reads rejected' => static fn () => $plan(null, null, null, []),
    'empty statement name rejected' => static fn () => $plan(['' => $cache['stable-user-query']]),
    'missing pages rejected' => static fn () => $plan(['bad' => ['state' => 'ready']]),
    'empty pages rejected' => static fn () => $plan(['bad' => ['pages' => []]]),
    'zero hot page rejected' => static fn () => $plan(null, [0 => $page('bad')]),
    'short hot page rejected' => static fn () => $plan(null, [1 => 'short']),
    'zero rollback page rejected' => static fn () => $plan(null, null, [0 => $page('bad')]),
    'short rollback page rejected' => static fn () => $plan(null, null, [1 => 'short']),
    'bad retry page rejected' => static fn () => $plan(null, null, null, [0]),
    'zero cache page rejected' => static fn () => $plan(['bad' => ['pages' => [0 => ['image' => $page('bad'), 'source_id' => 'a', 'epoch' => 1]]]]),
    'short cache page rejected' => static fn () => $plan(['bad' => ['pages' => [1 => ['image' => 'short', 'source_id' => 'a', 'epoch' => 1]]]]),
    'empty cache source rejected' => static fn () => $plan(['bad' => ['pages' => [1 => ['image' => $page('bad'), 'source_id' => '', 'epoch' => 1]]]]),
    'bad cache epoch rejected' => static fn () => $plan(['bad' => ['pages' => [1 => ['image' => $page('bad'), 'source_id' => 'a', 'epoch' => 0]]]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal statement cache current source next104 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
