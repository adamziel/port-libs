<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next192.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next192-users.sqlite';
$master = '/srv/wp-content/database/wp-next192.sqlite-mj';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$sourceId = 'pager-reader-cache-member-token-next192';
$publication = 192;
$masterDigest = hash('sha256', 'next192-current-master-source');
$recoverySequence = 77;
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
$before = [
    1 => $formatPage('next192 stale schema before member token recovery', 0, 1, 21, 0x57503132),
    2 => $page('next192 stale wp_options root before member token recovery'),
    3 => $page('next192 stale active_plugins before member token recovery'),
    4 => $page('next192 unchanged rewrite rules before member token recovery'),
    5 => $page('next192 unchanged optionmeta before member token recovery'),
    6 => $page('next192 stale multisite usermeta before member token recovery'),
    7 => $page('next192 unchanged transient before member token recovery'),
    8 => $page('next192 stale cron before member token recovery'),
];
$recovered = [
    1 => $formatPage('next192 current schema after member token recovery', 4, 2, 22, 0x57503133),
    2 => $page('next192 recovered wp_options root after member token recovery'),
    3 => $page('next192 recovered active_plugins after member token recovery'),
    6 => $page('next192 recovered multisite usermeta after member token recovery'),
    8 => $page('next192 recovered cron after member token recovery'),
];
$databaseBytes = implode('', $before);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 22, 0x57503133]));
$oldFormatSignature = hash('sha256', implode('|', [512, 0, 1, 21, 0x57503132]));
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
$currentRecoveredDigest = $recoveredDigest($recovered);
$currentTokens = [
    $mainJournal => 'dev=8:ino=192:size=4096:mtime=9200:generation=main-current',
    $usersJournal => 'dev=8:ino=193:size=1024:mtime=9201:generation=users-current',
];
$oldUsersTokens = [
    $mainJournal => $currentTokens[$mainJournal],
    $usersJournal => 'dev=8:ino=193:size=1024:mtime=9100:generation=users-prior',
];
$oldMainTokens = [
    $mainJournal => 'dev=8:ino=192:size=4096:mtime=9100:generation=main-prior',
    $usersJournal => $currentTokens[$usersJournal],
];
$tokenDigest = static function (array $tokens): string {
    ksort($tokens, SORT_STRING);
    $parts = [];
    foreach ($tokens as $member => $token) {
        $parts[] = $member . '=' . $token;
    }

    return hash('sha256', implode('|', $parts));
};
$currentTokenDigest = $tokenDigest($currentTokens);
$oldTokenDigest = $tokenDigest($oldUsersTokens);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 192,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $currentRecoveredDigest,
    'member_journal_tokens' => $currentTokens,
], $extra);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-member-token', $recovered[1], ['shared' => true]),
    2 => $cacheEntry('root-refreshed-member-token', $before[2]),
    3 => $cacheEntry('active-stale-users-member-token', $recovered[3], ['member_journal_tokens' => $oldUsersTokens]),
    4 => $cacheEntry('rewrite-stale-main-member-token', $before[4], ['member_journal_tokens' => $oldMainTokens]),
    5 => $cacheEntry('optionmeta-stale-format', $before[5], ['format_signature' => $oldFormatSignature]),
    6 => $cacheEntry('usermeta-stale-recovery-sequence', $recovered[6], ['recovery_sequence' => 76]),
    7 => $cacheEntry('transient-dirty-member-token', $before[7], ['dirty' => true]),
    8 => $cacheEntry('cron-pinned-stale-member-token', $before[8], ['pinned' => true]),
];
$reads = static fn (string $memberDigest = null, string $source = null, int $epoch = 192): array => array_map(
    static fn (int $pageNumber): array => [
        'reader_id' => 'read-' . $pageNumber,
        'page_number' => $pageNumber,
        'source_id' => $source ?? $sourceId,
        'epoch' => $epoch,
        'format_signature' => $formatSignature,
        'publication_generation' => $publication,
        'master_source_digest' => $masterDigest,
        'recovery_sequence' => $recoverySequence,
        'recovered_page_set_digest' => $currentRecoveredDigest,
        'member_journal_token_digest' => $memberDigest ?? $currentTokenDigest,
    ],
    range(1, 8),
);
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $tokens = null,
    ?array $recoveredPages = null,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext192(
    $database,
    $master,
    $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $sourceId,
    192,
    $publication,
    $masterDigest,
    $recoverySequence,
    $tokens ?? $currentTokens,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};
$opCount = static function (string $op) use ($plan): int {
    return count(array_filter($plan()['operations'], static fn (array $operation): bool => ($operation['op'] ?? '') === $op));
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next192'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_rechecks_attached_member_journal_tokens_before_current_source_reuse'],
    'current token digest' => [static fn (): mixed => $plan()['current_member_journal_token_digest'], $currentTokenDigest],
    'current token main' => [static fn (): mixed => $plan()['current_member_journal_tokens'][$mainJournal], $currentTokens[$mainJournal]],
    'current token users' => [static fn (): mixed => $plan()['current_member_journal_tokens'][$usersJournal], $currentTokens[$usersJournal]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'member invalidated pages' => [static fn (): mixed => $plan()['member_token_invalidated_cache_page_numbers'], [3, 4]],
    'recovery invalidated pages preserved' => [static fn (): mixed => $plan()['recovery_invalidated_cache_page_numbers'], [6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema row admitted' => [static fn (): mixed => $row('schema-retained-member-token')['member_token_admitted'], true],
    'schema row reason' => [static fn (): mixed => $row('schema-retained-member-token')['member_token_reason'], 'reader_cache_attached_member_journal_tokens_match_current_source'],
    'root row admitted' => [static fn (): mixed => $row('root-refreshed-member-token')['member_token_admitted'], true],
    'users token row rejected' => [static fn (): mixed => $row('active-stale-users-member-token')['member_token_reason'], 'reader_cache_attached_member_journal_token_changed'],
    'main token row rejected' => [static fn (): mixed => $row('rewrite-stale-main-member-token')['member_token_reason'], 'reader_cache_attached_member_journal_token_changed'],
    'stale format base reason' => [static fn (): mixed => $row('optionmeta-stale-format')['member_token_reason'], 'reader_cache_format_signature_mismatch_after_master_recovery'],
    'stale recovery base reason' => [static fn (): mixed => $row('usermeta-stale-recovery-sequence')['member_token_reason'], 'reader_cache_recovery_sequence_predates_master_journal_source'],
    'dirty base reason' => [static fn (): mixed => $row('transient-dirty-member-token')['member_token_reason'], 'dirty_reader_cache_cannot_cross_recovered_format_ticket'],
    'pinned base reason' => [static fn (): mixed => $row('cron-pinned-stale-member-token')['member_token_reason'], 'pinned_reader_cache_image_predates_format_ticket'],
    'users mismatched journal' => [static fn (): mixed => $row('active-stale-users-member-token')['mismatched_member_journals'], [$usersJournal]],
    'main mismatched journal' => [static fn (): mixed => $row('rewrite-stale-main-member-token')['mismatched_member_journals'], [$mainJournal]],
    'cache token digest differs' => [static fn (): mixed => $row('active-stale-users-member-token')['cache_member_journal_token_digest'], $oldTokenDigest],
    'current token digest on row' => [static fn (): mixed => $row('active-stale-users-member-token')['current_member_journal_token_digest'], $currentTokenDigest],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['read_cache_hits']['read-2'], true],
    'read users token miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read main token miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read member token current' => [static fn (): mixed => $plan()['next_reads'][0]['member_journal_token_current'], true],
    'read stale token source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-member-token-fence-current-source-next192'],
    'read stale token reason' => [static fn (): mixed => $plan()['next_reads'][2]['member_token_reason'], 'reader_cache_reopened_after_attached_member_journal_token_change'],
    'read stale ticket misses retained cache' => [static fn (): mixed => $plan(null, $reads($oldTokenDigest))['read_cache_hits']['read-1'], false],
    'read stale ticket reason' => [static fn (): mixed => $plan(null, $reads($oldTokenDigest))['next_reads'][0]['member_token_reason'], 'reader_ticket_attached_member_journal_token_predates_current_source'],
    'read root prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-2'], 'next192 recovered wp_options root after member token recovery'],
    'read active prefix' => [static fn (): mixed => $plan()['read_prefixes']['read-3'], 'next192 recovered active_plugins after member token recovery'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-3', 'read-4', 'read-5', 'read-6', 'read-7', 'read-8']],
    'operation invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_attached_member_journal_after_master_current_source_next192', array_column($plan()['operations'], 'op'), true), true],
    'operation invalidate count' => [static fn (): mixed => $opCount('invalidate_reader_cache_attached_member_journal_after_master_current_source_next192'), 2],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next192', $plan()['dependencies'], true), true],
    'dependency token fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-attached-member-journal-token-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next186', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next186'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'single admitted cache has no member invalidation' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-token', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 192, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest]])['member_token_invalidated_cache_page_numbers'], []],
    'all current single read hits' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-member-token', $recovered[1])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 192, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest]])['read_cache_hits']['read-1'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next192 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing current member token rejected' => static fn () => $plan(null, null, [$mainJournal => $currentTokens[$mainJournal]]),
    'empty current member token rejected' => static fn () => $plan(null, null, [$mainJournal => $currentTokens[$mainJournal], $usersJournal => '']),
    'missing cache tokens rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_tokens' => null])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 192, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest]]),
    'missing cache member token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $recovered[1], ['member_journal_tokens' => [$mainJournal => $currentTokens[$mainJournal]]])], [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 192, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => $currentTokenDigest]]),
    'empty read digest rejected' => static fn () => $plan(null, [['reader_id' => 'read-1', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 192, 'format_signature' => $formatSignature, 'publication_generation' => $publication, 'master_source_digest' => $masterDigest, 'recovery_sequence' => $recoverySequence, 'recovered_page_set_digest' => $currentRecoveredDigest, 'member_journal_token_digest' => '']]),
    'base bad recovery sequence rejected' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext192($database, $master, $masterBytes, $databaseBytes, $pageSize, $recovered, $cache(), $reads(), $sourceId, 192, $publication, $masterDigest, 0, $currentTokens),
    'base unaligned database rejected' => static fn () => $plan(null, null, null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next192 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
