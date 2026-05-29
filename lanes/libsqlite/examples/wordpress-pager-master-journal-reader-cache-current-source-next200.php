<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next200.sqlite';
$master = '/srv/wp-content/database/wp-next200.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next200-users.sqlite-journal';
$masterBytes = $usersJournal . "\n" . $journal . "\n";
$checkpoint = 2007;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$memberToken = static fn (string $member, int $generation): string => 'member-source-generation:' . substr(hash('sha256', $master . '|' . hash('sha256', $masterBytes) . '|' . $checkpoint . '|' . $member . '|' . $generation), 0, 40);
$pageDigest = static fn (int $pageNumber, string $image, string $member, string $token): string => hash('sha256', $pageNumber . '|' . $member . '|' . $token . '|' . hash('sha256', $image));
$readerToken = static function (string $group, array $parts): string {
    sort($parts, SORT_NATURAL);

    return 'reader-member-generation:' . substr(hash('sha256', $group . '|' . implode('|', $parts)), 0, 40);
};

$before = [
    1 => $page('wp next200 schema before member generation'),
    2 => $page('wp next200 active_plugins before member generation'),
    3 => $page('wp next200 users before member generation'),
];
$current = [
    2 => ['image' => $page('wp next200 active_plugins after member generation'), 'member_journal_path' => $journal],
    3 => ['image' => $before[3], 'member_journal_path' => $usersJournal],
];
$tokens = [$journal => $memberToken($journal, 11), $usersJournal => $memberToken($usersJournal, 12)];
$oldUsersToken = $memberToken($usersJournal, 11);
$source = static function (int $pageNumber) use ($before, $current, $tokens, $pageDigest): array {
    if (isset($current[$pageNumber])) {
        $member = $current[$pageNumber]['member_journal_path'];
        $image = $current[$pageNumber]['image'];
        return [$member, $tokens[$member], $image, $pageDigest($pageNumber, $image, $member, $tokens[$member])];
    }
    $member = 'database-image-before-master-journal-recovery-next200';
    $token = 'database-before-master-member-generation-next200';
    return [$member, $token, $before[$pageNumber], $pageDigest($pageNumber, $before[$pageNumber], $member, $token)];
};
$schemaPart = '1:' . $source(1)[1] . ':' . $source(1)[3];
$optionsPart2 = '2:' . $source(2)[1] . ':' . $source(2)[3];
$optionsPart3 = '3:' . $source(3)[1] . ':' . $source(3)[3];
$schemaToken = $readerToken('wp-schema-reader', [$schemaPart]);
$optionsToken = $readerToken('wp-options-reader', [$optionsPart2, $optionsPart3]);
$oldOptionsToken = $readerToken('wp-options-reader', [
    '2:' . $tokens[$journal] . ':' . $source(2)[3],
    '3:' . $oldUsersToken . ':' . $pageDigest(3, $before[3], $usersJournal, $oldUsersToken),
]);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext200(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-retained', 'image' => $before[1], 'member_journal_path' => $source(1)[0], 'member_generation_token' => $source(1)[1], 'reader_id' => 'schema-cache', 'reader_transaction_id' => 'wp-schema-reader', 'reader_member_generation_token' => $schemaToken, 'page_source_digest' => $source(1)[3], 'source_id' => 'wp-next200-current-source', 'epoch' => 200],
        2 => ['label' => 'wp-active-plugins-refresh', 'image' => $before[2], 'member_journal_path' => $journal, 'member_generation_token' => $tokens[$journal], 'reader_id' => 'active-cache', 'reader_transaction_id' => 'wp-options-reader', 'reader_member_generation_token' => $optionsToken, 'page_source_digest' => $source(2)[3], 'source_id' => 'wp-next200-current-source', 'epoch' => 200],
        3 => ['label' => 'wp-users-byte-identical-old-member', 'image' => $before[3], 'member_journal_path' => $usersJournal, 'member_generation_token' => $oldUsersToken, 'reader_id' => 'users-cache', 'reader_transaction_id' => 'wp-options-reader', 'reader_member_generation_token' => $oldOptionsToken, 'page_source_digest' => $pageDigest(3, $before[3], $usersJournal, $oldUsersToken), 'source_id' => 'wp-next200-current-source', 'epoch' => 200],
    ],
    [
        ['reader_id' => 'schema-reader', 'reader_transaction_id' => 'wp-schema-reader', 'page_number' => 1, 'member_journal_path' => $source(1)[0], 'member_generation_token' => $source(1)[1], 'reader_member_generation_token' => $schemaToken, 'page_source_digest' => $source(1)[3]],
        ['reader_id' => 'active-reader', 'reader_transaction_id' => 'wp-options-reader', 'page_number' => 2, 'member_journal_path' => $journal, 'member_generation_token' => $tokens[$journal], 'reader_member_generation_token' => $optionsToken, 'page_source_digest' => $source(2)[3]],
        ['reader_id' => 'users-reader', 'reader_transaction_id' => 'wp-options-reader', 'page_number' => 3, 'member_journal_path' => $usersJournal, 'member_generation_token' => $tokens[$usersJournal], 'reader_member_generation_token' => $optionsToken, 'page_source_digest' => $source(3)[3]],
    ],
    $current,
    [$journal => 11, $usersJournal => 12],
    'wp-next200-current-source',
    200,
    $checkpoint,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next200',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'activeCacheHit' => $plan['read_cache_hits']['active-reader'],
    'usersCacheHit' => $plan['read_cache_hits']['users-reader'],
    'wordpressUse' => 'A copied WordPress options reader spanning active_plugins and a byte-identical users page reopens after the users rollback-journal generation advances under a master-journal recovery.',
    'dependencyClosure' => 'no new support component needed; this is a bounded pager reader-cache admission fence over lane-local master-journal metadata',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next200'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== []
    || $summary['invalidatedCachePages'] !== [2, 3]
    || $summary['activeCacheHit'] !== false
    || $summary['usersCacheHit'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next200 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next200 self-test passed\n";
