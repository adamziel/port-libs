<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next186.sqlite';
$master = '/srv/wp-content/database/wp-next186.sqlite-mj';
$sourceId = 'wp-next186-recovery-source';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next186-users.sqlite-journal\n";
$publication = 186;
$masterDigest = hash('sha256', 'wp-next186-current-master-source');
$recoverySequence = 42;
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 16, 0x57504f53]));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 16), 60, 4);
    $page = substr_replace($page, pack('N', 0x57504f53), 68, 4);

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
    1 => $formatPage('wp next186 stale header before recovery set'),
    2 => $page('wp next186 stale options page before recovery set'),
    3 => $page('wp next186 stale active plugins before recovery set'),
];
$recovered = [
    1 => $formatPage('wp next186 current header after recovery set'),
    2 => $page('wp next186 recovered options page after recovery set'),
    3 => $page('wp next186 recovered active plugins after recovery set'),
];
$currentRecoveredDigest = $recoveredDigest($recovered);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext186(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['label' => 'wp-schema-recovery-retained', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 186, 'reader_id' => 'schema-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest],
        2 => ['label' => 'wp-options-recovery-refresh', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 186, 'reader_id' => 'options-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest],
        3 => ['label' => 'wp-active-plugins-old-recovery-set', 'image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 186, 'reader_id' => 'active-reader', 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => 41, 'recovered_page_set_digest' => hash('sha256', 'wp-next186-prior-recovered-page-set')],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest],
        ['reader_id' => 'options-read', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest],
        ['reader_id' => 'active-read', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 186, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest],
    ],
    $sourceId,
    186,
    $publication,
    $masterDigest,
    $recoverySequence,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next186',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'recoveryInvalidatedPages' => $plan['recovery_invalidated_cache_page_numbers'],
    'optionsReadPrefix' => $plan['read_prefixes']['options-read'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-read'],
    'wordpressUse' => 'A copied wp_options database prevents a reader-cache page from a prior recovered-page set from surviving a newer master-journal recovery.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next186'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['recoveryInvalidatedPages'] !== [3]
    || $summary['optionsReadPrefix'] !== 'wp next186 recovered options page after recovery set'
    || $summary['activePluginsCacheHit'] !== false
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next186 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next186 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
