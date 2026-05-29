<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next237.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next237-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'pager-reader-cache-schema-format-next237';
$publication = 237;
$masterDigest = hash('sha256', 'next237-master-source');
$recoverySequence = 237;
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterBytesDigest = hash('sha256', $masterBytes);
$masterToken = 'dev=8:ino=2370:size=96:mtime=23700:generation=master-current';
$databaseToken = 'dev=8:ino=2379:size=2560:mtime=23799:generation=database-current';
$currentHeaderDigest = hash('sha256', 'schema-cookie=237;schema-format=4;change-counter=77;version-valid-for=77;user-version=607;application-id=0x575037');
$currentPageCount = 5;
$currentCounter = 77;
$currentUserVersion = 607;
$currentApplicationId = 0x575037;
$currentSchemaFormat = 4;
$oldSchemaFormat = 3;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $format) use ($pageSize, $currentUserVersion, $currentApplicationId, $currentCounter): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', $currentCounter), 24, 4);
    $page = substr_replace($page, pack('N', $format), 44, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $currentUserVersion), 60, 4);
    $page = substr_replace($page, pack('N', $currentApplicationId), 68, 4);
    $page = substr_replace($page, pack('N', $currentCounter), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next237 stale schema before schema-format recovery', $oldSchemaFormat),
    2 => $page('next237 stale wp_options root before schema-format recovery'),
    3 => $page('next237 stale active_plugins before schema-format recovery'),
    4 => $page('next237 stale autoload cache before schema-format recovery'),
    5 => $page('next237 stale rewrite cache before schema-format recovery'),
];
$recovered = [
    1 => $formatPage('next237 current schema after schema-format recovery', $currentSchemaFormat),
    2 => $page('next237 current wp_options root after schema-format recovery'),
    3 => $page('next237 current active_plugins after schema-format recovery'),
    4 => $page('next237 current autoload cache after schema-format recovery'),
    5 => $page('next237 current rewrite cache after schema-format recovery'),
];
$databaseBytes = implode('', $before);
$members = [$mainJournal, $usersJournal];
$orderDigest = hash('sha256', implode("\n", $members));
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, $currentUserVersion, $currentApplicationId]));
$currentRecoveredDigest = $recoveredDigest($recovered);
$tokens = [
    $mainJournal => 'dev=8:ino=2371:size=4096:mtime=23701:generation=main-current',
    $usersJournal => 'dev=8:ino=2372:size=1024:mtime=23702:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-237'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-237'),
];
$tokenDigest = $mapDigest($tokens);
$headerDigest = $mapDigest($headers);
$cacheEntry = static fn (string $label, string $image, int $schemaFormat, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 237,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'database_header_digest' => $currentHeaderDigest,
    'database_page_count' => $currentPageCount,
    'database_change_counter' => $currentCounter,
    'version_valid_for' => $currentCounter,
    'user_version' => $currentUserVersion,
    'application_id' => $currentApplicationId,
    'schema_format_number' => $schemaFormat,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-schema-format', $recovered[1], $currentSchemaFormat),
    2 => $cacheEntry('root-refreshed-schema-format', $before[2], $currentSchemaFormat),
    3 => $cacheEntry('active-stale-schema-format', $recovered[3], $oldSchemaFormat),
    4 => $cacheEntry('autoload-stale-schema-format', $recovered[4], $oldSchemaFormat),
    5 => $cacheEntry('rewrite-retained-schema-format', $recovered[5], $currentSchemaFormat),
];
$reads = static fn (int $schemaFormat = null): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $sourceId,
        'epoch' => 237,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $tokenDigest,
        'member_journal_header_digest' => $headerDigest,
        'master_member_order_digest' => $orderDigest,
        'master_journal_file_token' => $masterToken,
        'master_journal_bytes_digest' => $masterBytesDigest,
        'database_file_token' => $databaseToken,
        'database_header_digest' => $currentHeaderDigest,
        'database_page_count' => $currentPageCount,
        'database_change_counter' => $currentCounter,
        'version_valid_for' => $currentCounter,
        'user_version' => $currentUserVersion,
        'application_id' => $currentApplicationId,
        'schema_format_number' => $schemaFormat ?? $currentSchemaFormat,
    ],
    range(1, 5),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?int $schemaFormat = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext237(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    237,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $currentHeaderDigest,
    $currentPageCount,
    $currentCounter,
    $currentCounter,
    $currentUserVersion,
    $currentApplicationId,
    $schemaFormat ?? $currentSchemaFormat,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$read = static function (string $readerId) use ($plan): array {
    foreach ($plan()['next_reads'] as $read) {
        if ($read['reader_id'] === $readerId) {
            return $read;
        }
    }
    throw new RuntimeException('missing read ' . $readerId);
};
$opCount = static function (string $op) use ($plan): int {
    return count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next237'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_schema_format_number_before_current_source_reuse'],
    'current schema format' => [static fn (): mixed => $plan()['current_schema_format_number'], $currentSchemaFormat],
    'inherits user version' => [static fn (): mixed => $plan()['current_user_version'], $currentUserVersion],
    'inherits application id' => [static fn (): mixed => $plan()['current_application_id'], $currentApplicationId],
    'schema format invalidated pages' => [static fn (): mixed => $plan()['schema_format_number_invalidated_cache_page_numbers'], [3, 4]],
    'all invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 5]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4']],
    'read hit retained schema' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read hit refreshed root' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read hit stale active' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read hit stale autoload' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'schema format op count' => [static fn (): mixed => $opCount('invalidate_reader_cache_schema_format_number_after_current_source_next237'), 2],
    'schema format operation reason' => [static fn (): mixed => array_values(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === 'invalidate_reader_cache_schema_format_number_after_current_source_next237'))[0]['reason'], 'reader_cache_reopened_after_schema_format_number_change'],
    'dependency next237' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next237', $plan()['dependencies'], true), true],
    'dependency schema format fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-schema-format-fence', $plan()['dependencies'], true), true],
    'non overlap mentions next234' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next234 user_version/application_id'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'row retained admitted' => [static fn (): mixed => $row('schema-retained-schema-format')['schema_format_number_admitted'], true],
    'row retained reason' => [static fn (): mixed => $row('schema-retained-schema-format')['schema_format_number_reason'], 'reader_cache_schema_format_number_matches_current_source'],
    'row retained format' => [static fn (): mixed => $row('schema-retained-schema-format')['cache_schema_format_number'], $currentSchemaFormat],
    'row retained current format' => [static fn (): mixed => $row('schema-retained-schema-format')['current_schema_format_number'], $currentSchemaFormat],
    'row retained format matches' => [static fn (): mixed => $row('schema-retained-schema-format')['schema_format_number_matches'], true],
    'row refreshed admitted' => [static fn (): mixed => $row('root-refreshed-schema-format')['schema_format_number_admitted'], true],
    'row stale admitted false' => [static fn (): mixed => $row('active-stale-schema-format')['schema_format_number_admitted'], false],
    'row stale reason' => [static fn (): mixed => $row('active-stale-schema-format')['schema_format_number_reason'], 'reader_cache_schema_format_number_changed_after_master_journal_recovery'],
    'row stale format' => [static fn (): mixed => $row('active-stale-schema-format')['cache_schema_format_number'], $oldSchemaFormat],
    'row stale mismatch' => [static fn (): mixed => $row('active-stale-schema-format')['schema_format_number_matches'], false],
    'read retained format current' => [static fn (): mixed => $read('read-1')['schema_format_number_current'], true],
    'read retained format value' => [static fn (): mixed => $read('read-1')['schema_format_number'], $currentSchemaFormat],
    'read stale source' => [static fn (): mixed => $read('read-3')['source'], 'master-journal-reader-cache-schema-format-fence-current-source-next237'],
    'read stale reason' => [static fn (): mixed => $read('read-3')['schema_format_number_reason'], 'reader_cache_reopened_after_schema_format_number_change'],
    'stale read ticket reason' => [static fn (): mixed => $plan(null, $reads($oldSchemaFormat))['next_reads'][0]['schema_format_number_reason'], 'reader_ticket_schema_format_number_predates_current_source'],
    'stale read ticket reopens all readers' => [static fn (): mixed => $plan(null, $reads($oldSchemaFormat))['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5']],
    'future current format invalidates admitted cache' => [static fn (): mixed => $plan(null, null, 1)['schema_format_number_invalidated_cache_page_numbers'], [1, 2, 3, 4, 5]],
    'future current format changes source digest' => [static fn (): mixed => $plan(null, null, 1)['source_digest'] !== $plan()['source_digest'], true],
    'all fresh cache no invalidations' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentSchemaFormat)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['schema_format_number_invalidated_cache_page_numbers'], []],
    'all fresh cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('single-fresh', $recovered[1], $currentSchemaFormat)], [['reader_id' => 'fresh-read', 'page_number' => 1] + $reads()[0]])['requires_reader_reopen'], false],
    'database bytes fixture length' => [static fn (): mixed => strlen($databaseBytes), $pageSize * 5],
    'format page carries current schema format' => [static fn (): mixed => unpack('N', substr($recovered[1], 44, 4))[1], $currentSchemaFormat],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next237 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'zero current schema format rejected' => static fn () => $plan(null, null, 0),
    'too large current schema format rejected' => static fn () => $plan(null, null, 5),
    'zero cache schema format rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], 0)]),
    'too large cache schema format rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], 5)]),
    'missing cache schema format rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $recovered[1], 4), ['schema_format_number' => true])]),
    'zero read schema format rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(0)[0]]),
    'too large read schema format rejected' => static fn () => $plan(null, [['reader_id' => 'bad-read', 'page_number' => 1] + $reads(5)[0]]),
    'missing read schema format rejected' => static fn () => $plan(null, [array_diff_key($reads()[0], ['schema_format_number' => true])]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next237 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
