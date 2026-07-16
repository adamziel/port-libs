<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::pageCachePressureProfile([
    'scenario' => 'application-pcache-pressure',
    'primary_cache_size' => 16,
    'peer_cache_size' => 12,
    'dirty_schema_pages' => 14,
    'first_schema_pages' => 6,
    'peer_pinned_pages' => 2,
    'index_burst_pages' => 16,
    'extra_dirty_pages' => 1,
    'expanded_cache_size' => 24,
    'scan_pages' => 23,
    'reduced_cache_size' => 19,
    'corrupt_reload_pages' => 3,
    'reread_pages' => 18,
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'pcache.test');
    assert($profile['combined_cache_max'] === 28);
    assert($profile['over_limit_steps'] === ['index-burst-over-limit', 'extra-schema-over-limit', 'peer-rollback-frees-pinned-page']);
    assert($profile['peer_rollback_frees_pinned_page'] === true);
    assert($profile['commit_recycles_to_global_limit'] === true);
    assert($profile['events'][14]['stats']['current'] === 3);
    assert(in_array('sqlite-pcache-peer-read-lock-overlimit-free', $profile['dependencies'], true));
    echo "application-vfs-pcache-pressure self-test passed\n";
    return;
}

echo json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
