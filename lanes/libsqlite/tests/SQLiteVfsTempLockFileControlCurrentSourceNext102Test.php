<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsTempLockingFileControlCurrentSourcePlan;

$plan102 = static fn (array $ops, array $options = []): array => SQLiteVfsTempLockingFileControlCurrentSourcePlan::planTempLockDataVersionFileControl(
    $ops,
    $options + ['temp_dir' => '/tmp/wp-cache', 'connection_id' => 'WP Import 102'],
);

$staleTemp = static function () use ($plan102): array {
    static $result = null;
    if ($result === null) {
        $result = $plan102([
            ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
            ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
            ['op' => 'lock', 'handle' => 'temp-wp-import-102-1', 'value' => 'reserved'],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-1', 'control' => 'chunk_size', 'value' => 4096],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-2', 'control' => 'data_version'],
            ['op' => 'lock', 'handle' => 'temp-wp-import-102-2', 'value' => 'pending'],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-2', 'control' => 'size_hint', 'value' => 8192],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-1', 'control' => 'data_version'],
            ['op' => 'close', 'handle' => 'temp-wp-import-102-1'],
            ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
            ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-3', 'control' => 'data_version'],
        ]);
    }

    return $result;
};

$lockGate = static fn (): array => $plan102([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'sorter', 'delete_on_close' => false],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 1024],
    ['op' => 'lock', 'value' => 'shared'],
    ['op' => 'filecontrol', 'control' => 'size_hint', 'value' => 2048],
    ['op' => 'lock', 'value' => 'exclusive'],
    ['op' => 'filecontrol', 'control' => 'size_hint', 'value' => 2048],
]);

$deleteOnClose = static fn (): array => $plan102([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'delete-journal', 'delete_on_close' => true],
    ['op' => 'lock', 'value' => 'reserved'],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 2048],
    ['op' => 'close', 'source' => 'temp'],
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'delete-journal', 'delete_on_close' => true],
    ['op' => 'filecontrol', 'control' => 'data_version'],
]);

$sourceIsolation = static fn (): array => $plan102([
    ['op' => 'open', 'source' => 'main', 'suffix' => 'db', 'delete_on_close' => false],
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ['op' => 'lock', 'source' => 'main', 'value' => 'reserved'],
    ['op' => 'filecontrol', 'source' => 'main', 'control' => 'chunk_size', 'value' => 4096],
    ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'data_version'],
    ['op' => 'lock', 'source' => 'temp', 'value' => 'reserved'],
    ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'chunk_size', 'value' => 8192],
    ['op' => 'filecontrol', 'source' => 'main', 'control' => 'data_version'],
]);

$memory = static fn (): array => $plan102([
    ['op' => 'open', 'source' => 'temp', 'suffix' => 'memory-sorter', 'delete_on_close' => false],
    ['op' => 'lock', 'value' => 'reserved'],
    ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 512],
    ['op' => 'filecontrol', 'control' => 'data_version'],
    ['op' => 'close', 'source' => 'temp'],
], ['temp_store' => 'memory']);

return [
    'vfs temp lock filecontrol current source next102 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-temp-lock-data-version-filecontrol', $staleTemp()['dependencies'], true)),
    'vfs temp lock filecontrol current source next102 final status ok' => static fn (TestRunner $t) => $t->same('ok', $staleTemp()['status']),
    'vfs temp lock filecontrol current source next102 first open generation' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['events'][0]['next']['handles']['temp-wp-import-102-1']['source_generation']),
    'vfs temp lock filecontrol current source next102 second open generation' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['events'][1]['next']['handles']['temp-wp-import-102-2']['source_generation']),
    'vfs temp lock filecontrol current source next102 writer lock ok' => static fn (TestRunner $t) => $t->same('ok', $staleTemp()['events'][2]['status']),
    'vfs temp lock filecontrol current source next102 first write ok' => static fn (TestRunner $t) => $t->same('ok', $staleTemp()['events'][3]['status']),
    'vfs temp lock filecontrol current source next102 first write changed' => static fn (TestRunner $t) => $t->same(true, $staleTemp()['events'][3]['changed']),
    'vfs temp lock filecontrol current source next102 first write bumps generation' => static fn (TestRunner $t) => $t->same(2, $staleTemp()['events'][3]['source_generation']),
    'vfs temp lock filecontrol current source next102 stale reader data version status' => static fn (TestRunner $t) => $t->same('ok', $staleTemp()['events'][4]['status']),
    'vfs temp lock filecontrol current source next102 stale reader value' => static fn (TestRunner $t) => $t->same(2, $staleTemp()['events'][4]['value']),
    'vfs temp lock filecontrol current source next102 stale reader opened generation' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['events'][4]['opened_generation']),
    'vfs temp lock filecontrol current source next102 stale reader marked stale' => static fn (TestRunner $t) => $t->same(true, $staleTemp()['events'][4]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 second write lock ok' => static fn (TestRunner $t) => $t->same('ok', $staleTemp()['events'][5]['status']),
    'vfs temp lock filecontrol current source next102 second write bumps generation' => static fn (TestRunner $t) => $t->same(3, $staleTemp()['events'][6]['source_generation']),
    'vfs temp lock filecontrol current source next102 original writer stale after second write' => static fn (TestRunner $t) => $t->same(true, $staleTemp()['events'][7]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 original writer sees latest' => static fn (TestRunner $t) => $t->same(3, $staleTemp()['events'][7]['value']),
    'vfs temp lock filecontrol current source next102 close unlocks persisted lock' => static fn (TestRunner $t) => $t->same('unlocked', $staleTemp()['events'][8]['next']['persistent_locks']['temp:temp:.stmt-journal']),
    'vfs temp lock filecontrol current source next102 reopen starts latest generation' => static fn (TestRunner $t) => $t->same(3, $staleTemp()['events'][9]['next']['handles']['temp-wp-import-102-3']['source_generation']),
    'vfs temp lock filecontrol current source next102 reopened data version fresh' => static fn (TestRunner $t) => $t->same(false, $staleTemp()['events'][10]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 reopened data version value' => static fn (TestRunner $t) => $t->same(3, $staleTemp()['events'][10]['value']),
    'vfs temp lock filecontrol current source next102 final open count' => static fn (TestRunner $t) => $t->same(2, $staleTemp()['next']['open_count']),
    'vfs temp lock filecontrol current source next102 final temp open count' => static fn (TestRunner $t) => $t->same(2, $staleTemp()['next']['open_by_source']['temp']),
    'vfs temp lock filecontrol current source next102 final temp locked count' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['next']['locked_by_source']['temp']),
    'vfs temp lock filecontrol current source next102 final persisted controls' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['next']['persistent_control_count']),
    'vfs temp lock filecontrol current source next102 final current source temp' => static fn (TestRunner $t) => $t->same('temp', $staleTemp()['next']['current_source']),
    'vfs temp lock filecontrol current source next102 persistent generation counted' => static fn (TestRunner $t) => $t->same(1, $staleTemp()['next']['persistent_generation_count']),

    'vfs temp lock filecontrol current source next102 unlocked write blocked' => static fn (TestRunner $t) => $t->same('blocked', $lockGate()['events'][1]['status']),
    'vfs temp lock filecontrol current source next102 unlocked write reason' => static fn (TestRunner $t) => $t->same('requires_reserved_or_exclusive_temp_lock', $lockGate()['events'][1]['reason']),
    'vfs temp lock filecontrol current source next102 shared write blocked' => static fn (TestRunner $t) => $t->same('blocked', $lockGate()['events'][3]['status']),
    'vfs temp lock filecontrol current source next102 exclusive write ok' => static fn (TestRunner $t) => $t->same('ok', $lockGate()['events'][5]['status']),
    'vfs temp lock filecontrol current source next102 exclusive write generation' => static fn (TestRunner $t) => $t->same(2, $lockGate()['events'][5]['source_generation']),
    'vfs temp lock filecontrol current source next102 lockgate no blocked controls persisted' => static fn (TestRunner $t) => $t->same(['size_hint' => 2048], $lockGate()['current']['handles']['temp-wp-import-102-1']['controls']),

    'vfs temp lock filecontrol current source next102 delete close deleted' => static fn (TestRunner $t) => $t->same(true, $deleteOnClose()['events'][3]['deleted']),
    'vfs temp lock filecontrol current source next102 delete close clears generation' => static fn (TestRunner $t) => $t->same([], $deleteOnClose()['events'][3]['next']['persistent_generations']),
    'vfs temp lock filecontrol current source next102 delete reopen generation reset' => static fn (TestRunner $t) => $t->same(1, $deleteOnClose()['events'][4]['next']['handles']['temp-wp-import-102-2']['source_generation']),
    'vfs temp lock filecontrol current source next102 delete data version fresh' => static fn (TestRunner $t) => $t->same(false, $deleteOnClose()['events'][5]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 delete data version one' => static fn (TestRunner $t) => $t->same(1, $deleteOnClose()['events'][5]['value']),

    'vfs temp lock filecontrol current source next102 main write isolated generation' => static fn (TestRunner $t) => $t->same(2, $sourceIsolation()['events'][3]['source_generation']),
    'vfs temp lock filecontrol current source next102 temp still fresh before temp write' => static fn (TestRunner $t) => $t->same(false, $sourceIsolation()['events'][4]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 temp data version remains one' => static fn (TestRunner $t) => $t->same(1, $sourceIsolation()['events'][4]['value']),
    'vfs temp lock filecontrol current source next102 temp write isolated generation' => static fn (TestRunner $t) => $t->same(2, $sourceIsolation()['events'][6]['source_generation']),
    'vfs temp lock filecontrol current source next102 main data version remains two' => static fn (TestRunner $t) => $t->same(2, $sourceIsolation()['events'][7]['value']),
    'vfs temp lock filecontrol current source next102 main data version fresh' => static fn (TestRunner $t) => $t->same(false, $sourceIsolation()['events'][7]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 source isolation generation count' => static fn (TestRunner $t) => $t->same(2, $sourceIsolation()['next']['persistent_generation_count']),

    'vfs temp lock filecontrol current source next102 memory write ok' => static fn (TestRunner $t) => $t->same('ok', $memory()['events'][2]['status']),
    'vfs temp lock filecontrol current source next102 memory data version local' => static fn (TestRunner $t) => $t->same(2, $memory()['events'][3]['value']),
    'vfs temp lock filecontrol current source next102 memory data version fresh' => static fn (TestRunner $t) => $t->same(false, $memory()['events'][3]['stale_current_source']),
    'vfs temp lock filecontrol current source next102 memory controls not persisted' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_control_count']),
    'vfs temp lock filecontrol current source next102 memory generations not persisted' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_generation_count']),

    'vfs temp lock filecontrol current source next102 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsTempLockingFileControlCurrentSourcePlan::planTempLockDataVersionFileControl([])),
];
