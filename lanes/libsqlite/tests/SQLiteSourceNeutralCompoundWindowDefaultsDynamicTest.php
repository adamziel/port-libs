<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowRowValueUpsertCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';
$compoundWindowSourceFiles = static function () use ($sourceRoot): array {
    $files = [];
    foreach ([
        $sourceRoot . '/SQLiteCompound*.php',
        $sourceRoot . '/SQLite*Window*.php',
    ] as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            $files[] = $file;
        }
    }
    $files[] = $sourceRoot . '/SQLiteSelectRecursiveWindowMaterializePlan.php';
    sort($files, SORT_STRING);

    return array_values(array_unique($files));
};
$partitionedWindowSourceFile = $sourceRoot . '/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';
$compoundWindowFixtureFiles = [
    $libsqliteRoot . '/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php',
    $libsqliteRoot . '/examples/application-compound-having-window-current-source-next128.php',
];

$compoundWindowSourceInventory = static function () use ($compoundWindowSourceFiles): array {
    $relative = array_map(static fn (string $file): string => basename($file), $compoundWindowSourceFiles());

    return [
        'countAtLeast40' => count($relative) >= 40,
        'hasRecursiveLimitPlan' => in_array('SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php', $relative, true),
        'hasWindowRowValuePlan' => in_array('SQLiteWindowRowValueUpsertCurrentSourcePlan.php', $relative, true),
        'hasVdbeWindowCursor' => in_array('SQLiteVdbeWindowAggregateCursor.php', $relative, true),
    ];
};

$compoundWindowSourceMatches = static function () use ($compoundWindowSourceFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'opt' . 'ion_walk',
        'auto' . 'load',
        'Auto' . 'load',
        'plugin' . '_',
        'application-' . 'option',
        'Application ' . 'option',
    ];
    $pattern = '/(?:\bwp\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($compoundWindowSourceFiles() as $sourceFile) {
        $contents = file_get_contents($sourceFile);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$sourceFile}");
        }

        if (preg_match_all($pattern, $contents, $fileMatches, PREG_OFFSET_CAPTURE) < 1) {
            continue;
        }

        $relative = str_replace($libsqliteRoot . '/', '', $sourceFile);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match[0]}";
        }
    }

    return $matches;
};

$partitionedWindowSourceMatches = static function () use ($partitionedWindowSourceFile, $libsqliteRoot): array {
    $contents = file_get_contents($partitionedWindowSourceFile);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$partitionedWindowSourceFile}");
    }

    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'Auto' . 'load',
        'blog' . '_id',
    ];
    $pattern = '/(?:\bwp\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    if (preg_match_all($pattern, $contents, $fileMatches, PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $relative = str_replace($libsqliteRoot . '/', '', $partitionedWindowSourceFile);

    return array_map(
        static fn (array $match): string => "{$relative}: {$match[0]}",
        $fileMatches[0],
    );
};

$compoundWindowFixtureMatches = static function () use ($compoundWindowFixtureFiles, $libsqliteRoot): array {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
        'site' . 'url',
        'active' . '_plugins',
        'plug' . 'in_',
        'theme' . '_',
    ];
    $pattern = '/(?:\bhome\b|' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $matches = [];

    foreach ($compoundWindowFixtureFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }

        if (preg_match_all($pattern, $contents, $fileMatches) < 1) {
            continue;
        }

        $relative = str_replace($libsqliteRoot . '/', '', $file);
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$match}";
        }
    }

    return $matches;
};

$rowValueWindowDefaultSignatureMatches = static function () use ($partitionedWindowSourceFile, $libsqliteRoot): array {
    $contents = file_get_contents($partitionedWindowSourceFile);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$partitionedWindowSourceFile}");
    }

    $functions = [
        'executeRetryWindowPlan',
        'executeReturningWindowRollbackRetry',
        'executeReturningWindowDigests',
        'executeCurrentRowWindowFrames',
        'executeReplayPairWindow',
        'executeStatementWindowMetrics',
        'executePeerGroupWindowReceipt',
        'executePairCurrentRowFrames',
        'executeChainedStatementWindows',
        'executeTupleFrameWindowReceipt',
        'executeTransitionChainWindow',
        'executeYieldGateWindow',
        'executeFilteredReleaseWindow',
        'executeExcludeGroupWindow',
        'executePublicationResumeBarrier',
        'executeChunkedYieldResumeWindow',
        'executeExcludeTiesWindowPlan',
        'executeSourceDigestHandoff',
        'executePublicationWindowFence',
        'executeChunkCursorReleaseWindow',
    ];
    $terms = [
        'opt' . 'ion_id',
        'blog' . '_id',
        'auto' . 'load',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
    $relative = str_replace($libsqliteRoot . '/', '', $partitionedWindowSourceFile);
    $matches = [];

    foreach ($functions as $function) {
        $startMarker = "public static function {$function}(";
        $start = strpos($contents, $startMarker);
        if ($start === false) {
            throw new RuntimeException("Unable to find row-value window default signature {$function}");
        }
        $end = strpos($contents, '): array {', $start);
        if ($end === false || $end <= $start) {
            throw new RuntimeException("Unable to isolate row-value window default signature {$function}");
        }

        $signature = substr($contents, $start, $end - $start);
        if (preg_match_all($pattern, $signature, $fileMatches) < 1) {
            continue;
        }
        foreach ($fileMatches[0] as $match) {
            $matches[] = "{$relative}: {$function}: {$match}";
        }
    }

    return $matches;
};

$windowRowValueDefaults = static fn (): array => SQLiteWindowRowValueUpsertCurrentSourcePlan::execute(
    [
        ['key_name' => 'base_url', 'version' => 1, 'priority' => 10, 'load_policy' => 'yes', 'key_value' => 'old'],
        ['key_name' => 'site_title', 'version' => 1, 'priority' => 5, 'load_policy' => 'no', 'key_value' => 'title'],
    ],
    [
        ['key_name' => 'base_url', 'version' => 1, 'priority' => 12, 'load_policy' => 'yes', 'key_value' => 'new'],
        ['key_name' => 'module_registry', 'version' => 1, 'priority' => 4, 'load_policy' => 'no', 'key_value' => 'module'],
    ],
    ['key_name'],
    ['version', 'priority'],
);

$windowRowValueKeys = static function () use ($windowRowValueDefaults): array {
    $row = $windowRowValueDefaults()['window_rows'][0] ?? [];
    ksort($row);

    return array_keys($row);
};

$returningWindowDefaultRows = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry(
    [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'old', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 10],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'module_registry', 'key_value' => 'enabled', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 12],
        ],
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('yield', key_value || ':yield', bytes + 1) WHERE setting_id = 1 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('attempt', key_value || ':attempt', bytes + 1) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('retry', key_value || ':retry', bytes + 2) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [['tenant_id', 'key_name']],
);

$peerWindowDefaultRows = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePeerGroupWindowReceipt(
    [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'old', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 10],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'module_registry', 'key_value' => 'enabled', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 12],
            ['setting_id' => 3, 'tenant_id' => 2, 'key_name' => 'cache_policy', 'key_value' => 'cache', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 6],
        ],
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('yield', key_value || ':yield', bytes + 1) WHERE setting_id = 1 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('attempt', key_value || ':attempt', bytes + 1) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('retry', key_value || ':retry', bytes + 2) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
        "DELETE FROM app_settings WHERE setting_id = 3 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [['tenant_id', 'key_name']],
);

$yieldGateDefaultRows = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeYieldGateWindow(
    [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'old', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 10],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'module_registry', 'key_value' => 'enabled', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 12],
            ['setting_id' => 3, 'tenant_id' => 2, 'key_name' => 'cache_policy', 'key_value' => 'cache', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 6],
        ],
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('yield', key_value || ':yield', bytes + 1) WHERE setting_id = 1 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('attempt', key_value || ':attempt', bytes + 1) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [
        "UPDATE app_settings SET (status, key_value, bytes) = ('retry', key_value || ':retry', bytes + 2) WHERE setting_id = 2 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
        "DELETE FROM app_settings WHERE setting_id = 3 RETURNING setting_id, tenant_id, key_name, status, key_value, bytes ORDER BY setting_id",
    ],
    [['tenant_id', 'key_name']],
);

return [
    'source-neutral compound window defaults dynamic source inventory covers current family' => static fn (TestRunner $t) => $t->same([
        'countAtLeast40' => true,
        'hasRecursiveLimitPlan' => true,
        'hasWindowRowValuePlan' => true,
        'hasVdbeWindowCursor' => true,
    ], $compoundWindowSourceInventory()),
    'source-neutral compound window defaults dynamic source has no legacy setting table terms' => static fn (TestRunner $t) => $t->same([], $compoundWindowSourceMatches()),
    'source-neutral compound having window fixture defaults use setting terms' => static fn (TestRunner $t) => $t->same([], $compoundWindowFixtureMatches()),
    'source-neutral partitioned row-value window source defaults use setting terms' => static fn (TestRunner $t) => $t->same([], $partitionedWindowSourceMatches()),
    'source-neutral row-value window retry signatures default to setting ids' => static fn (TestRunner $t) => $t->same([], $rowValueWindowDefaultSignatureMatches()),
    'source-neutral row-value window defaults expose generic key names' => static fn (TestRunner $t) => $t->same([
        'action',
        'first_key_name',
        'frame',
        'frame_count',
        'frame_priority_concat',
        'frame_priority_sum',
        'key_name',
        'last_key_name',
        'sequence',
    ], $windowRowValueKeys()),
    'source-neutral row-value returning window default rowid is setting id' => static function (TestRunner $t) use ($returningWindowDefaultRows): void {
        $plan = $returningWindowDefaultRows();

        $t->same([1], array_column($plan['yield_window'], 'setting_id'));
        $t->same([2], array_column($plan['retry_window'], 'setting_id'));
        $t->same(false, array_key_exists('option_id', $plan['retry_window'][0]));
        $t->same('retry', array_column($plan['current_source_tables']['app_settings'], 'status', 'setting_id')[2]);
    },
    'source-neutral row-value peer window default rowid is setting id' => static function (TestRunner $t) use ($peerWindowDefaultRows): void {
        $plan = $peerWindowDefaultRows();

        $t->same([2, 3], $plan['retry_peer_group_ids_next240']);
        $t->same(false, array_key_exists('option_id', $plan['retry_peer_groups_next240'][0]));
        $t->same([2, 3], $plan['retry_peer_group_receipt_next240']['retry_ids']);
        $t->same([1, 2], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
    'source-neutral row-value yield gate default rowid is setting id' => static function (TestRunner $t) use ($yieldGateDefaultRows): void {
        $plan = $yieldGateDefaultRows();

        $t->same([1], array_column($plan['yield_phase_tickets_next245'], 'setting_id'));
        $t->same([2, 3], array_column($plan['retry_phase_tickets_next245'], 'setting_id'));
        $t->same(false, array_key_exists('option_id', $plan['yield_phase_tickets_next245'][0]));
        $t->same(false, array_key_exists('option_id', $plan['retry_phase_tickets_next245'][0]));
        $t->same([1, 2], array_column($plan['current_source_tables']['app_settings'], 'setting_id'));
    },
];
