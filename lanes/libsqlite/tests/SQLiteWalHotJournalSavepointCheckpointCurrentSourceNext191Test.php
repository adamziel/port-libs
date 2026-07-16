<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next191.sqlite';
$walPath = $databasePath . '-wal';
$token = ['id' => 'next191-current-source-token', 'epoch' => 191];
$basePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next188',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'current_source_token' => $token,
    'current_commit_hook' => 9100,
    'current_schema_cookie' => 52,
    'hook_digest' => hash('sha256', 'next191-base-hook-digest'),
    'operation_names' => ['publish_commit_hook_current_source_next188'],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188',
        'sqlite-wal-commit-hook-prepared-statement-reader-admission',
    ],
];
$image = static fn (string $label): string => hash('sha256', $label);
$cacheEntries = [
    ['name' => 'options-root-current', 'page' => 5, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52, 'image_sha256' => $image('options-root-current')],
    ['name' => 'schema-hot-journal', 'page' => 1, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52, 'image_sha256' => $image('schema-hot')],
    ['name' => 'active-plugins-savepoint', 'page' => 3, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52, 'image_sha256' => $image('active-plugins-savepoint')],
    ['name' => 'cron-checkpoint', 'page' => 4, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52],
    ['name' => 'stale-token', 'page' => 6, 'source_id' => 'old-token', 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52],
    ['name' => 'stale-epoch', 'page' => 7, 'source_id' => $token['id'], 'epoch' => 190, 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52],
    ['name' => 'stale-hook', 'page' => 8, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9099, 'observed_schema_cookie' => 52],
    ['name' => 'stale-schema', 'page' => 9, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 51],
    ['name' => 'dirty-page', 'page' => 10, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52, 'dirty' => true],
    ['name' => 'closed-page', 'page' => 11, 'source_id' => $token['id'], 'epoch' => $token['epoch'], 'observed_commit_hook' => 9100, 'observed_schema_cookie' => 52, 'closed' => true],
];

$plan = static fn (?array $base = null, ?array $entries = null, array $checkpointPages = [4], array $hotPages = [1, 2], array $savepointPages = [3, 4]): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next191Plan(
        $base ?? $basePlan,
        $entries ?? $cacheEntries,
        $checkpointPages,
        $hotPages,
        $savepointPages
    );
$ok = static fn (): array => $plan();
$allCurrent = static fn (): array => $plan(null, [$cacheEntries[0]]);
$badBase = $basePlan;
$badBase['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next188';

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next191'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'page_cache_reused_only_for_current_untouched_pages_after_hot_journal_savepoint_checkpoint'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $walPath],
    'token' => [static fn (): mixed => $ok()['current_source_token'], $token],
    'hook' => [static fn (): mixed => $ok()['current_commit_hook'], 9100],
    'schema cookie' => [static fn (): mixed => $ok()['current_schema_cookie'], 52],
    'checkpoint pages' => [static fn (): mixed => $ok()['checkpoint_pages'], [4]],
    'hot pages' => [static fn (): mixed => $ok()['hot_journal_pages'], [1, 2]],
    'savepoint pages' => [static fn (): mixed => $ok()['savepoint_pages'], [3, 4]],
    'touched pages' => [static fn (): mixed => $ok()['touched_pages'], [1, 2, 3, 4]],
    'row count' => [static fn (): mixed => count($ok()['cache_rows']), 10],
    'retained names' => [static fn (): mixed => $ok()['retained_cache_names'], ['options-root-current']],
    'invalidated names' => [static fn (): mixed => $ok()['invalidated_cache_names'], ['schema-hot-journal', 'active-plugins-savepoint', 'cron-checkpoint', 'stale-token', 'stale-epoch', 'stale-hook', 'stale-schema', 'dirty-page', 'closed-page']],
    'retained reason' => [static fn (): mixed => $ok()['cache_rows'][0]['reason'], 'cache_entry_matches_current_source_and_page_not_touched'],
    'hot reason' => [static fn (): mixed => $ok()['cache_rows'][1]['reason'], 'cache_entry_page_touched_by_hot-journal'],
    'savepoint reason' => [static fn (): mixed => $ok()['cache_rows'][2]['reason'], 'cache_entry_page_touched_by_savepoint'],
    'checkpoint savepoint reason' => [static fn (): mixed => $ok()['cache_rows'][3]['reason'], 'cache_entry_page_touched_by_checkpoint_and_savepoint'],
    'stale token reason' => [static fn (): mixed => $ok()['cache_rows'][4]['reason'], 'cache_entry_source_token_predates_current_source'],
    'stale epoch reason' => [static fn (): mixed => $ok()['cache_rows'][5]['reason'], 'cache_entry_source_token_predates_current_source'],
    'stale hook reason' => [static fn (): mixed => $ok()['cache_rows'][6]['reason'], 'cache_entry_commit_hook_predates_current_source'],
    'stale schema reason' => [static fn (): mixed => $ok()['cache_rows'][7]['reason'], 'cache_entry_schema_cookie_predates_current_source'],
    'dirty reason' => [static fn (): mixed => $ok()['cache_rows'][8]['reason'], 'cache_entry_dirty_before_checkpoint_publish'],
    'closed reason' => [static fn (): mixed => $ok()['cache_rows'][9]['reason'], 'cache_entry_closed'],
    'retained flag' => [static fn (): mixed => $ok()['cache_rows'][0]['retained'], true],
    'retained reload false' => [static fn (): mixed => $ok()['cache_rows'][0]['requires_reload'], false],
    'invalidated flag' => [static fn (): mixed => $ok()['cache_rows'][1]['retained'], false],
    'invalidated reload true' => [static fn (): mixed => $ok()['cache_rows'][1]['requires_reload'], true],
    'hot touched by' => [static fn (): mixed => $ok()['cache_rows'][1]['touched_by'], ['hot-journal']],
    'checkpoint savepoint touched by' => [static fn (): mixed => $ok()['cache_rows'][3]['touched_by'], ['checkpoint', 'savepoint']],
    'image sha preserved' => [static fn (): mixed => $ok()['cache_rows'][0]['image_sha256'], $image('options-root-current')],
    'null image allowed' => [static fn (): mixed => $ok()['cache_rows'][3]['image_sha256'], null],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['base_commit_hook_current_source', 'cache_entry_mix', 'all_touched_pages_accounted']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true]],
    'stale guard names' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'operation retain' => [static fn (): mixed => in_array('retain_page_cache_current_source_next191', $ok()['operation_names'], true), true],
    'operation invalidate' => [static fn (): mixed => in_array('invalidate_page_cache_current_source_next191', $ok()['operation_names'], true), true],
    'operation final' => [static fn (): mixed => end($ok()['operation_names']), 'publish_page_cache_admission_current_source_next191'],
    'cache digest length' => [static fn (): mixed => strlen($ok()['cache_digest']), 64],
    'dependency next188' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188', $ok()['dependencies'], true), true],
    'dependency next191' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next191', $ok()['dependencies'], true), true],
    'application dependency' => [static fn (): mixed => in_array('application-import-page-cache-current-source-fence', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'base plan embedded' => [static fn (): mixed => $ok()['base_plan']['hook_digest'], $basePlan['hook_digest']],
    'all current blocked' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next191'],
    'all current stale guards' => [static fn (): mixed => $allCurrent()['stale_guard_names'], ['cache_entry_mix']],
    'bad base blocked' => [static fn (): mixed => $plan($badBase)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next191'],
    'bad base stale guards' => [static fn (): mixed => $plan($badBase)['stale_guard_names'], ['base_commit_hook_current_source']],
    'empty touched blocked' => [static fn (): mixed => $plan(null, null, [], [], [])['stale_guard_names'], ['all_touched_pages_accounted']],
    'cache reasons count' => [static fn (): mixed => count($ok()['cache_reasons']), 10],
    'transition mentions reload' => [static fn (): mixed => str_contains($ok()['cache_rows'][1]['transition'], '>reload:'), true],
    'transition mentions retain' => [static fn (): mixed => str_contains($ok()['cache_rows'][0]['transition'], '>retain:'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next191 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing base status rejected' => static function () use ($basePlan, $plan): void {
        $bad = $basePlan;
        unset($bad['status']);
        $plan($bad);
    },
    'missing token rejected' => static function () use ($basePlan, $plan): void {
        $bad = $basePlan;
        unset($bad['current_source_token']);
        $plan($bad);
    },
    'bad token rejected' => static function () use ($basePlan, $plan): void {
        $bad = $basePlan;
        $bad['current_source_token'] = ['id' => '', 'epoch' => 1];
        $plan($bad);
    },
    'empty cache rejected' => static fn () => $plan(null, []),
    'missing cache name rejected' => static function () use ($cacheEntries, $plan): void {
        $bad = $cacheEntries;
        unset($bad[0]['name']);
        $plan(null, $bad);
    },
    'bad cache page rejected' => static function () use ($cacheEntries, $plan): void {
        $bad = $cacheEntries;
        $bad[0]['page'] = 0;
        $plan(null, $bad);
    },
    'missing hook rejected' => static function () use ($cacheEntries, $plan): void {
        $bad = $cacheEntries;
        unset($bad[0]['observed_commit_hook']);
        $plan(null, $bad);
    },
    'missing schema rejected' => static function () use ($cacheEntries, $plan): void {
        $bad = $cacheEntries;
        unset($bad[0]['observed_schema_cookie']);
        $plan(null, $bad);
    },
    'bad epoch rejected' => static function () use ($cacheEntries, $plan): void {
        $bad = $cacheEntries;
        $bad[0]['epoch'] = 0;
        $plan(null, $bad);
    },
    'bad page list rejected' => static fn () => $plan(null, null, ['4']),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next191 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
