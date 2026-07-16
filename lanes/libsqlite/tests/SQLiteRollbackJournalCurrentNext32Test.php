<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournalCurrentNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('wp_options schema before plugin import'),
    2 => $page('wp_options autoload values before plugin import'),
    3 => $page('wp_options option_name index before plugin import'),
];
$after = [
    2 => $page('wp_options autoload values after plugin import'),
    3 => $page('wp_options option_name index after plugin import'),
    4 => $page('wp_options new imported plugin row after import'),
];
$databaseBytes = implode('', $before);
$journalBytes = str_pad('rollback journal before copied wp_options import pages', $pageSize, "\0");

$plan = static fn (string $sync = 'full', string $journalMode = 'delete'): array => SQLiteRollbackJournalCurrentNextPlan::importTransaction(
    $databasePath,
    $databaseBytes,
    $journalBytes,
    $after,
    $pageSize,
    $sync,
    $journalMode
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'planned'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'page count before' => [static fn (): mixed => $plan()['database_page_count_before'], 3],
    'page count after grows' => [static fn (): mixed => $plan()['database_page_count_after'], 4],
    'dirty page count' => [static fn (): mixed => $plan()['dirty_page_count'], 3],
    'dirty pages sorted' => [static fn (): mixed => $plan()['dirty_pages'], [2, 3, 4]],
    'current reader first page remains before values' => [static fn (): mixed => $plan()['current_reader'][0]['image_prefix'], 'wp_options autoload values before plugin import'],
    'current reader second page remains before index' => [static fn (): mixed => $plan()['current_reader'][1]['image_prefix'], 'wp_options option_name index before plugin import'],
    'current reader new page is absent before commit' => [static fn (): mixed => $plan()['current_reader'][2]['image_length'], 0],
    'next reader first page sees imported values' => [static fn (): mixed => $plan()['next_reader'][0]['image_prefix'], 'wp_options autoload values after plugin import'],
    'next reader second page sees imported index' => [static fn (): mixed => $plan()['next_reader'][1]['image_prefix'], 'wp_options option_name index after plugin import'],
    'next reader new page sees import row' => [static fn (): mixed => $plan()['next_reader'][2]['image_prefix'], 'wp_options new imported plugin row after import'],
    'current reader changed flag false' => [static fn (): mixed => $plan()['current_reader'][0]['changed'], false],
    'next reader changed flag true' => [static fn (): mixed => $plan()['next_reader'][0]['changed'], true],
    'current bytes preserve old option value' => [static fn (): mixed => str_contains($plan()['current_database_bytes'], 'before plugin import'), true],
    'current bytes exclude new plugin row' => [static fn (): mixed => str_contains($plan()['current_database_bytes'], 'new imported plugin row'), false],
    'next bytes include imported value' => [static fn (): mixed => str_contains($plan()['next_database_bytes'], 'after plugin import'), true],
    'next bytes include extended page four' => [static fn (): mixed => strlen($plan()['next_database_bytes']), $pageSize * 4],
    'commit sync mode' => [static fn (): mixed => $plan()['commit']['sync_mode'], 'full'],
    'commit journal mode' => [static fn (): mixed => $plan()['commit']['journal_mode'], 'delete'],
    'commit operations count' => [static fn (): mixed => count($plan()['commit']['operations']), 8],
    'operation zero writes journal' => [static fn (): mixed => $plan()['commit']['operations'][0]['reason'], 'write_rollback_journal_before_database_pages'],
    'operation one syncs journal' => [static fn (): mixed => $plan()['commit']['operations'][1]['reason'], 'sync_rollback_journal'],
    'operation two writes page two' => [static fn (): mixed => $plan()['commit']['operations'][2]['reason'], 'write_dirty_database_page_2'],
    'operation three writes page three' => [static fn (): mixed => $plan()['commit']['operations'][3]['reason'], 'write_dirty_database_page_3'],
    'operation four writes page four' => [static fn (): mixed => $plan()['commit']['operations'][4]['reason'], 'write_dirty_database_page_4'],
    'operation five syncs database' => [static fn (): mixed => $plan()['commit']['operations'][5]['reason'], 'sync_committed_database_pages'],
    'operation six deletes journal' => [static fn (): mixed => $plan()['commit']['operations'][6]['reason'], 'delete_rollback_journal_after_commit'],
    'operation seven syncs directory' => [static fn (): mixed => $plan()['commit']['operations'][7]['reason'], 'persist_rollback_journal_commit_sidecar'],
    'visibility count follows operations' => [static fn (): mixed => count($plan()['visibility']), 8],
    'journal write not visible to current reader' => [static fn (): mixed => $plan()['visibility'][0]['commit_visible'], false],
    'journal sync not visible to current reader' => [static fn (): mixed => $plan()['visibility'][1]['reader_source'], 'current_reader_pre_commit'],
    'first database write still current reader' => [static fn (): mixed => $plan()['visibility'][2]['reader_page_prefixes'][0], 'wp_options autoload values before plugin import'],
    'second database write still current reader' => [static fn (): mixed => $plan()['visibility'][3]['reader_page_prefixes'][1], 'wp_options option_name index before plugin import'],
    'third database write still hides new page' => [static fn (): mixed => $plan()['visibility'][4]['reader_page_prefixes'][2], ''],
    'database sync still hides commit' => [static fn (): mixed => $plan()['visibility'][5]['commit_visible'], false],
    'journal delete makes commit visible' => [static fn (): mixed => $plan()['visibility'][6]['commit_visible'], true],
    'directory sync remains next reader' => [static fn (): mixed => $plan()['visibility'][7]['reader_source'], 'next_reader_after_commit'],
    'journal delete reader sees imported value' => [static fn (): mixed => $plan()['visibility'][6]['reader_page_prefixes'][0], 'wp_options autoload values after plugin import'],
    'directory sync reader sees new page' => [static fn (): mixed => $plan()['visibility'][7]['reader_page_prefixes'][2], 'wp_options new imported plugin row after import'],
    'database write counter after first write' => [static fn (): mixed => $plan()['visibility'][2]['database_pages_written'], 1],
    'database write counter after third write' => [static fn (): mixed => $plan()['visibility'][4]['database_pages_written'], 3],
    'dependencies include current next boundary' => [static fn (): mixed => in_array('sqlite-rollback-journal-current-next-reader-boundary', $plan()['dependencies'], true), true],
    'dependencies include application import' => [static fn (): mixed => in_array('application-import-rollback-journal-current-next', $plan()['dependencies'], true), true],
    'dependencies include durable ordering' => [static fn (): mixed => in_array('durable-journal-before-database-write', $plan()['dependencies'], true), true],
    'truncate mode uses truncate operation' => [static fn (): mixed => $plan('normal', 'truncate')['visibility'][6]['reason'], 'truncate_rollback_journal_after_commit'],
    'truncate mode commit visible at truncate' => [static fn (): mixed => $plan('normal', 'truncate')['visibility'][6]['commit_visible'], true],
    'persist mode uses zero header operation' => [static fn (): mixed => $plan('normal', 'persist')['visibility'][6]['reason'], 'zero_rollback_journal_header_after_commit'],
    'persist mode commit visible at zero header' => [static fn (): mixed => $plan('normal', 'persist')['visibility'][6]['commit_visible'], true],
    'sync off omits journal sync' => [static fn (): mixed => array_column($plan('off')['commit']['operations'], 'op'), ['write', 'write', 'write', 'write', 'delete']],
    'sync off commit visible at final operation' => [static fn (): mixed => $plan('off')['visibility'][4]['commit_visible'], true],
    'extra sync labels journal fullfsync' => [static fn (): mixed => $plan('extra')['commit']['operations'][1]['reason'], 'sync_rollback_journal_fullfsync'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rollback journal current next32 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['rollback journal current next32 rejects unaligned database'] = static function (TestRunner $t) use ($databasePath, $journalBytes, $after, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRollbackJournalCurrentNextPlan::importTransaction($databasePath, 'short', $journalBytes, $after, $pageSize));
};

$tests['rollback journal current next32 rejects empty database'] = static function (TestRunner $t) use ($databasePath, $journalBytes, $after, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRollbackJournalCurrentNextPlan::importTransaction($databasePath, '', $journalBytes, $after, $pageSize));
};

$tests['rollback journal current next32 rejects bad dirty page number'] = static function (TestRunner $t) use ($databasePath, $databaseBytes, $journalBytes, $pageSize, $page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRollbackJournalCurrentNextPlan::importTransaction($databasePath, $databaseBytes, $journalBytes, [0 => $page('bad')], $pageSize));
};

$tests['rollback journal current next32 rejects short dirty page image'] = static function (TestRunner $t) use ($databasePath, $databaseBytes, $journalBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRollbackJournalCurrentNextPlan::importTransaction($databasePath, $databaseBytes, $journalBytes, [2 => 'short'], $pageSize));
};

return $tests;
