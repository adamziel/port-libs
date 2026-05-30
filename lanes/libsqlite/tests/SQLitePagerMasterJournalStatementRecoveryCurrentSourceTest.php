<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerStatementRecoveryPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-pager-master-stmt-current-source-' . bin2hex(random_bytes(4));
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$mainPath = '/srv/www/wp-content/database/.ht.sqlite';
$metaPath = '/srv/www/wp-content/database/site-meta.sqlite';
$missPath = '/srv/www/wp-content/database/missing-stmt.sqlite';
$masterPath = '/srv/www/wp-content/database/.ht.sqlite-mj119';
$masterBytes = $mainPath . "-journal\n" . $metaPath . "-journal\n" . $missPath . "-journal\n";

$mainCurrent = $page('current-source main schema current') . $page('current-source main dirty options') . $page('current-source main dirty autoload');
$metaCurrent = $page('current-source meta schema current') . $page('current-source meta dirty network option');
$missCurrent = $page('current-source missing schema current') . $page('current-source missing dirty option');
$mainBefore = $page('current-source main options before statement');
$mainIndexBefore = $page('current-source main autoload before statement');
$metaBefore = $page('current-source meta option before statement');
$missBefore = $page('current-source missing option before stale input');

mkdir(dirname($root . $masterPath), 0777, true);
file_put_contents($root . $masterPath, $masterBytes);
file_put_contents($root . $mainPath, $mainCurrent);
file_put_contents($root . $metaPath, $metaCurrent);
file_put_contents($root . $missPath, $missCurrent);
file_put_contents($root . $mainPath . '-journal', 'current-source main outer rollback');
file_put_contents($root . $metaPath . '-journal', 'current-source meta outer rollback');
file_put_contents($root . $missPath . '-journal', 'current-source missing outer rollback');
file_put_contents($root . $mainPath . '-stmt-journal', 'current-source main statement journal exists');
file_put_contents($root . $metaPath . '-stmt-journal', 'current-source meta statement journal exists');

$databases = [
    [
        'database_path' => $mainPath,
        'statement_journal_path' => $mainPath . '-stmt-journal',
        'statement_pages' => [3 => $mainIndexBefore, 2 => $mainBefore],
    ],
    [
        'database_path' => $metaPath,
        'statement_journal_path' => $metaPath . '-stmt-journal',
        'statement_pages' => [2 => $metaBefore],
    ],
    [
        'database_path' => $missPath,
        'statement_journal_path' => $missPath . '-stmt-journal',
        'statement_pages' => [2 => $missBefore],
    ],
];

$plan = static fn (): array => SQLitePagerStatementRecoveryPlan::masterJournalStatementRecoveryCurrentNext(
    $masterPath,
    $masterBytes,
    [
        [
            'database_path' => $mainPath,
            'database_bytes' => $mainCurrent,
            'statement_journal_path' => $mainPath . '-stmt-journal',
            'statement_journal_exists' => true,
            'statement_pages' => [3 => $mainIndexBefore, 2 => $mainBefore],
        ],
        [
            'database_path' => $missPath,
            'database_bytes' => $missCurrent,
            'statement_journal_path' => $missPath . '-stmt-journal',
            'statement_journal_exists' => false,
            'statement_pages' => [2 => $missBefore],
        ],
    ],
    $pageSize
);

$appliedResult = null;
$apply = static function () use (&$appliedResult, $root, $masterPath, $databases, $pageSize): array {
    if ($appliedResult === null) {
        $appliedResult = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource(
            $masterPath,
            $databases,
            $pageSize
        );
    }

    return $appliedResult;
};

$cases = [
    'plan status recovers present statement journals' => [static fn (): mixed => $plan()['status'], 'master_journal_statement_recovered_current_next'],
    'plan database count' => [static fn (): mixed => $plan()['database_count'], 2],
    'plan recovered count excludes missing statement journal' => [static fn (): mixed => $plan()['recovered_database_count'], 1],
    'plan skipped count includes missing statement journal' => [static fn (): mixed => $plan()['skipped_database_count'], 1],
    'plan main statement journal exists' => [static fn (): mixed => $plan()['databases'][$mainPath]['statement_journal_exists'], true],
    'plan missing statement journal recorded absent' => [static fn (): mixed => $plan()['databases'][$missPath]['statement_journal_exists'], false],
    'plan missing statement journal reason' => [static fn (): mixed => $plan()['databases'][$missPath]['reason'], 'missing_statement_journal'],
    'plan missing statement journal is not recovered' => [static fn (): mixed => $plan()['databases'][$missPath]['recovered'], false],
    'plan missing statement journal action preserves' => [static fn (): mixed => $plan()['statement_journal_actions'][$missPath . '-stmt-journal'], 'preserve_statement_journal'],
    'plan main statement journal action deletes' => [static fn (): mixed => $plan()['statement_journal_actions'][$mainPath . '-stmt-journal'], 'delete_statement_journal_after_rollback'],
    'plan main next prefix restored' => [static fn (): mixed => $plan()['next_page_prefixes'][$mainPath][2], 'current-source main options before statement'],
    'plan missing next prefix remains dirty' => [static fn (): mixed => $plan()['next_page_prefixes'][$missPath][2], 'current-source missing dirty option'],
    'plan payload includes main rollback' => [static fn (): mixed => array_key_exists($mainPath . '#statement-rollback', $plan()['payloads']), true],
    'plan payload excludes missing rollback' => [static fn (): mixed => array_key_exists($missPath . '#statement-rollback', $plan()['payloads']), false],
    'plan operation count for one recovered database' => [static fn (): mixed => count($plan()['operations']), 4],
    'plan operation sequence' => [static fn (): mixed => array_column($plan()['operations'], 'op'), ['write', 'truncate', 'delete', 'sync_directory']],
    'plan dependency includes current next80 primitive' => [static fn (): mixed => in_array('sqlite-pager-master-journal-statement-recovery-current-next80', $plan()['dependencies'], true), true],
    'apply status' => [static fn (): mixed => $apply()['status'], 'applied'],
    'apply atomic' => [static fn (): mixed => $apply()['atomic'], true],
    'apply recovered two existing statement journals' => [static fn (): mixed => $apply()['recovery']['recovered_database_count'], 2],
    'apply skipped missing statement journal' => [static fn (): mixed => $apply()['recovery']['skipped_database_count'], 1],
    'apply missing reason' => [static fn (): mixed => $apply()['recovery']['databases'][$missPath]['reason'], 'missing_statement_journal'],
    'apply missing exists false' => [static fn (): mixed => $apply()['recovery']['databases'][$missPath]['statement_journal_exists'], false],
    'apply current source sees master' => [static fn (): mixed => $apply()['current_source']['master_journal_exists'], true],
    'apply current source paths' => [static fn (): mixed => $apply()['current_source']['database_paths'], [$mainPath, $metaPath, $missPath]],
    'apply main source byte count' => [static fn (): mixed => $apply()['current_source']['database_bytes'][$mainPath], $pageSize * 3],
    'apply meta source byte count' => [static fn (): mixed => $apply()['current_source']['database_bytes'][$metaPath], $pageSize * 2],
    'apply missing source byte count' => [static fn (): mixed => $apply()['current_source']['database_bytes'][$missPath], $pageSize * 2],
    'apply source statement path main' => [static fn (): mixed => $apply()['current_source']['statement_journal_paths'][$mainPath], $mainPath . '-stmt-journal'],
    'apply source statement path missing' => [static fn (): mixed => $apply()['current_source']['statement_journal_paths'][$missPath], $missPath . '-stmt-journal'],
    'apply deletes only present statement journals' => [static fn (): mixed => $apply()['files_deleted'], 2],
    'apply operation count' => [static fn (): mixed => $apply()['applied'], 7],
    'apply writes recovered bytes' => [static fn (): mixed => $apply()['bytes_written'], ($pageSize * 3) + ($pageSize * 2)],
    'apply truncates recovered bytes' => [static fn (): mixed => $apply()['bytes_truncated'], ($pageSize * 3) + ($pageSize * 2)],
    'apply directory sync once' => [static fn (): mixed => $apply()['directory_syncs'], 1],
    'apply dependency includes current-source' => [static fn (): mixed => in_array('sqlite-pager-statement-current-source-next84', $apply()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal statement recovery current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager master journal statement recovery current source applies images and preserves missing statement sidecar'] = static function (TestRunner $t) use ($root, $mainPath, $metaPath, $missPath, $mainBefore, $mainIndexBefore, $metaBefore, $missCurrent, $pageSize, $apply): void {
    $apply();
    $mainBytes = (string) file_get_contents($root . $mainPath);
    $metaBytes = (string) file_get_contents($root . $metaPath);
    $missBytes = (string) file_get_contents($root . $missPath);

    $t->same($mainBefore, substr($mainBytes, $pageSize, $pageSize));
    $t->same($mainIndexBefore, substr($mainBytes, $pageSize * 2, $pageSize));
    $t->same($metaBefore, substr($metaBytes, $pageSize, $pageSize));
    $t->same($missCurrent, $missBytes);
    $t->same(false, is_file($root . $mainPath . '-stmt-journal'));
    $t->same(false, is_file($root . $metaPath . '-stmt-journal'));
    $t->same(false, is_file($root . $missPath . '-stmt-journal'));
    $t->same(true, is_file($root . $mainPath . '-journal'));
    $t->same(true, is_file($root . $metaPath . '-journal'));
    $t->same(true, is_file($root . $missPath . '-journal'));
};

$tests['pager master journal statement recovery current source skips when all current statement journals are missing'] = static function (TestRunner $t) use ($root, $masterPath, $missPath, $missBefore, $pageSize): void {
    $result = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, [[
        'database_path' => $missPath,
        'statement_journal_path' => $missPath . '-stmt-journal',
        'statement_pages' => [2 => $missBefore],
    ]], $pageSize);

    $t->same('skipped', $result['status']);
    $t->same(0, $result['applied']);
    $t->same(0, $result['recovery']['recovered_database_count']);
    $t->same(1, $result['recovery']['skipped_database_count']);
    $t->same('missing_statement_journal', $result['recovery']['databases'][$missPath]['reason']);
    $t->same(false, $result['recovery']['databases'][$missPath]['statement_journal_exists']);
    $t->same([], $result['operations']);
};

$tests['pager master journal statement recovery current source rejects immutable writer before recovery'] = static function (TestRunner $t) use ($root, $masterPath, $databases, $pageSize): void {
    $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root, false, true))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, $databases, $pageSize));
};

return $tests;
