<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next189.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next189-users.sqlite';
$master = '/srv/wp-content/database/wp-next189.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$sourceId = 'wp-next189-member-journal-source';
$masterBytes = $journal . "\n" . $usersJournal . "\n";
$publication = 189;
$masterDigest = hash('sha256', 'wp-next189-current-master-source');
$recoverySequence = 77;
$memberDigests = [
    $journal => hash('sha256', 'wp-next189-current-main-journal'),
    $usersJournal => hash('sha256', 'wp-next189-current-users-journal'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 19, 0x57504f59]));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 19), 60, 4);
    $page = substr_replace($page, pack('N', 0x57504f59), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
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

$before = [
    1 => $formatPage('wp next189 stale header before member digest'),
    2 => $page('wp next189 stale options page before member digest'),
    3 => $page('wp next189 stale users page before member digest'),
];
$recovered = [
    1 => $formatPage('wp next189 current header after member digest'),
    2 => $page('wp next189 recovered options page after member digest'),
    3 => $page('wp next189 recovered users page after member digest'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planMemberJournalDigestFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['label' => 'wp-schema-member-retained', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 189, 'reader_id' => 'schema-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]],
        2 => ['label' => 'wp-options-member-refreshed', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 189, 'reader_id' => 'options-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]],
        3 => ['label' => 'wp-users-old-member-digest', 'image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 189, 'reader_id' => 'users-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $usersJournal, 'member_journal_digest' => hash('sha256', 'wp-next189-old-users-journal')],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]],
        ['reader_id' => 'options-read', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $journal, 'member_journal_digest' => $memberDigests[$journal]],
        ['reader_id' => 'users-read', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 189, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_path' => $usersJournal, 'member_journal_digest' => $memberDigests[$usersJournal]],
    ],
    $sourceId,
    189,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberDigests,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-member-journal-digest',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'memberInvalidatedPages' => $plan['member_journal_invalidated_cache_page_numbers'],
    'optionsReadPrefix' => $plan['read_prefixes']['options-read'],
    'usersCacheHit' => $plan['read_cache_hits']['users-read'],
    'wordpressUse' => 'A copied WordPress database with an attached users database prevents a reader-cache page from surviving when the master-journal member list is unchanged but the attached rollback journal digest changed.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next189'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['memberInvalidatedPages'] !== [3]
    || $summary['optionsReadPrefix'] !== 'wp next189 recovered options page after member digest'
    || $summary['usersCacheHit'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-member-journal-digest self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-member-journal-digest self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
