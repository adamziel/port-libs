<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next193.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next193-users.sqlite';
$master = '/srv/wp-content/database/wp-next193.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterBytes = $journal . "\n" . $usersJournal . "\n";
$masterByteDigest = hash('sha256', $masterBytes);
$stableToken = 'stable-master-read:' . substr(hash('sha256', $master . '|' . $masterByteDigest . '|2'), 0, 40);
$oldStableToken = 'stable-master-read:' . substr(hash('sha256', $master . '|' . hash('sha256', 'old-master') . '|2'), 0, 40);
$memberDigests = [
    $journal => hash('sha256', 'wp-next193-main-journal'),
    $usersJournal => hash('sha256', 'wp-next193-users-journal'),
];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 23), 60, 4);
    $page = substr_replace($page, pack('N', 0x57505033), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next193 stale schema before stable master read'),
    2 => $page('wp next193 stale alloptions before stable master read'),
    3 => $page('wp next193 stale active_plugins before stable master read'),
    4 => $page('wp next193 stale users before stable master read'),
];
$recovered = [
    1 => $formatPage('wp next193 recovered schema after stable master read'),
    2 => $page('wp next193 recovered alloptions after stable master read'),
    3 => $page('wp next193 recovered active_plugins after stable master read'),
    4 => $page('wp next193 recovered users after stable master read'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $number => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad page fixture');
        }
        $parts[] = $number . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$sourceId = 'wp-next193-current-source';
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 23, 0x57505033]));
$publication = 193;
$masterDigest = hash('sha256', 'wp-next193-master-source');
$sequence = 1930;
$pageSetDigest = $recoveredDigest($recovered);
$cacheEntry = static fn (string $label, string $image, string $memberPath, string $token): array => [
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 193,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $sequence,
    'recovered_page_set_digest' => $pageSetDigest,
    'member_journal_path' => $memberPath,
    'member_journal_digest' => $memberDigests[$memberPath],
    'stable_master_read_token' => $token,
];
$read = static fn (int $pageNumber, string $memberPath, string $token): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 193,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $sequence,
    'recovered_page_set_digest' => $pageSetDigest,
    'member_journal_path' => $memberPath,
    'member_journal_digest' => $memberDigests[$memberPath],
    'stable_master_read_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::stableMasterReadTokenFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $journal, $stableToken),
        2 => $cacheEntry('alloptions', $before[2], $journal, $stableToken),
        3 => $cacheEntry('active_plugins', $recovered[3], $journal, $oldStableToken),
        4 => $cacheEntry('users', $recovered[4], $usersJournal, $stableToken),
    ],
    [
        $read(1, $journal, $stableToken),
        $read(2, $journal, $stableToken),
        $read(3, $journal, $stableToken),
        $read(4, $usersJournal, $stableToken),
    ],
    $sourceId,
    193,
    $publication,
    $masterDigest,
    $sequence,
    $memberDigests,
    [$masterByteDigest, $masterByteDigest],
);

$summary = [
    'status' => $plan['status'],
    'stableToken' => $plan['stable_master_read']['token'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'invalidated' => $plan['invalidated_cache_page_numbers'],
    'activePluginsHit' => $plan['read_cache_hits']['read-3'],
    'alloptionsPrefix' => $plan['read_prefixes']['read-2'],
    'wordpressUse' => 'A copied WordPress database reuses wp_options reader-cache pages only after two identical reads of the current master journal; active_plugins reopens when its ticket came from an older master-journal read.',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next193'
        || $summary['retained'] !== [1, 4]
        || $summary['refreshed'] !== [2]
        || $summary['invalidated'] !== [3]
        || $summary['activePluginsHit'] !== false
        || $summary['alloptionsPrefix'] !== 'wp next193 recovered alloptions after stable master read'
    ) {
        throw new RuntimeException('WordPress pager master-journal reader-cache next193 smoke failed');
    }

    echo "wordpress-pager-master-journal-reader-cache-stable-master-read-token-fence self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
