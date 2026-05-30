<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/wp-content/database/wp-options-next202.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-users-next202.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $reserved, int $encoding, int $userVersion, int $applicationId) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr($reserved), 20, 1);
    $page = substr_replace($page, pack('N', $encoding), 56, 4);
    $page = substr_replace($page, pack('N', $userVersion), 60, 4);
    $page = substr_replace($page, pack('N', $applicationId), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $number => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad WordPress page fixture');
        }
        $parts[] = $number . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};

$before = [
    1 => $formatPage('wp next202 schema before playback recovery', 0, 1, 202, 0x57502022),
    2 => $page('wp_options alloptions page before playback recovery'),
    3 => $page('wp_options active_plugins before playback recovery'),
    4 => $page('wp_users roles before playback recovery'),
];
$recovered = [
    1 => $formatPage('wp next202 schema after playback recovery', 4, 2, 203, 0x57502023),
    2 => $page('wp_options alloptions page after playback recovery'),
    3 => $page('wp_options active_plugins after playback recovery'),
    4 => $page('wp_users roles after playback recovery'),
];
$sourceId = 'wp-master-reader-cache-playback-current-source';
$publication = 202;
$recoverySequence = 202;
$masterDigest = hash('sha256', 'wp-master-source-next202');
$recoveredSet = $recoveredDigest($recovered);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 203, 0x57502023]));
$tokens = [
    $mainJournal => 'dev=8:ino=2202:size=4096:mtime=220200:generation=main-current',
    $usersJournal => 'dev=8:ino=2203:size=1024:mtime=220201:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-header-next202'),
    $usersJournal => hash('sha256', 'users-header-next202'),
];
$playback = [
    $mainJournal => hash('sha256', 'main-playback-schema-options-active-plugins-next202'),
    $usersJournal => hash('sha256', 'users-playback-roles-next202'),
];
$priorUsersPlayback = [
    $mainJournal => $playback[$mainJournal],
    $usersJournal => hash('sha256', 'users-playback-prior-roles-next201'),
];
$entry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 202,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $recoveredSet,
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'member_journal_playback_digests' => $playback,
], $extra);
$reads = array_map(static fn (int $pageNumber): array => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 202,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $recoveredSet,
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'member_journal_playback_digest' => $mapDigest($playback),
], [1, 2, 3, 4]);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPlaybackDigestFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => $entry('schema-retained', $recovered[1], ['shared' => true]),
        2 => $entry('alloptions-refreshed', $before[2]),
        3 => $entry('active-plugins-retained', $recovered[3]),
        4 => $entry('roles-stale-users-playback', $recovered[4], ['member_journal_playback_digests' => $priorUsersPlayback]),
    ],
    $reads,
    $sourceId,
    202,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $playback,
);

if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next202') {
    throw new RuntimeException('Unexpected pager reader cache next202 status');
}
if ($plan['retained_cache_page_numbers'] !== [1, 3]) {
    throw new RuntimeException('WordPress retained reader-cache pages changed unexpectedly');
}
if ($plan['refreshed_cache_page_numbers'] !== [2]) {
    throw new RuntimeException('WordPress alloptions page did not refresh from recovered source');
}
if ($plan['member_playback_invalidated_cache_page_numbers'] !== [4]) {
    throw new RuntimeException('Stale wp_users playback digest was not invalidated');
}
if ($plan['read_cache_hits']['wp-read-4'] !== false) {
    throw new RuntimeException('Stale wp_users reader ticket did not reopen');
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next202 self-test passed\n";
