<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$pageSizes = [1024, 2048, 4096];
$assignments = ['exclusive', 'normal', 'invalid'];
$lockStages = [
    'peer-before-exclusive-read',
    'exclusive-shared-blocks-peer-write',
    'peer-reserved-commit-blocked',
    'exclusive-write-blocks-peer-read',
    'normal-assignment-keeps-lock',
    'normal-read-releases-lock',
];
$journalEvents = ['initial', 'begin-delete', 'commit', 'rollback', 'normal-release'];
$statementEvents = [
    'normal-before-commit',
    'normal-after-commit',
    'exclusive-begin',
    'exclusive-statement',
    'exclusive-after-commit',
    'normal-release',
];
$hotJournalCases = ['copied-hot-journal', 'empty-database-with-stray-journal'];

$expectedCounterSequence = static function (int $normalBefore, int $exclusiveWrites, int $normalAfter): array {
    $counter = 1;
    $sequence = [$counter];
    for ($i = 0; $i < $normalBefore; $i++) {
        $sequence[] = ++$counter;
    }
    for ($i = 0; $i < $exclusiveWrites; $i++) {
        if ($i === 0) {
            ++$counter;
        }
        $sequence[] = $counter;
    }
    for ($i = 0; $i < $normalAfter; $i++) {
        if ($i > 0) {
            ++$counter;
        }
        $sequence[] = $counter;
    }

    return $sequence;
};

$case = 0;
foreach (range(1, 120) as $round) {
    $pageSize = $pageSizes[$round % count($pageSizes)];
    $cachePages = 32 + ($round % 37);
    $rowCount = 24 + ($round % 41);

    $matrix = [
        [
            'group' => 'exclusive-1',
            'scenario' => sprintf('exclusive-1.%03d.dynamic', $round),
            'options' => [
                'assignment' => $assignments[$round % count($assignments)],
                'attached_databases' => $round % 4,
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-2',
            'scenario' => sprintf('exclusive-2.%03d.dynamic', $round),
            'options' => [
                'stage' => $lockStages[$round % count($lockStages)],
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-3',
            'scenario' => sprintf('exclusive-3.%03d.dynamic', $round),
            'options' => [
                'event' => $journalEvents[$round % count($journalEvents)],
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-4',
            'scenario' => sprintf('exclusive-4.%03d.dynamic', $round),
            'options' => [
                'mutation_rounds' => 1 + ($round % 5),
                'default_cache_size' => 8 + ($round % 9),
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-5',
            'scenario' => sprintf('exclusive-5.%03d.dynamic', $round),
            'options' => [
                'event' => $statementEvents[$round % count($statementEvents)],
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-6',
            'scenario' => sprintf('exclusive-6.%03d.dynamic', $round),
            'options' => [
                'case' => $hotJournalCases[$round % count($hotJournalCases)],
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive-7',
            'scenario' => sprintf('exclusive-7.%03d.dynamic', $round),
            'options' => [
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive2-1',
            'scenario' => sprintf('exclusive2-1.%03d.dynamic', $round),
            'options' => [
                'initial_change_counter' => $round % 13,
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive2-2',
            'scenario' => sprintf('exclusive2-2.%03d.dynamic', $round),
            'options' => [
                'corrupt_bytes' => 1024 + ($round * 17),
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
        [
            'group' => 'exclusive2-3',
            'scenario' => sprintf('exclusive2-3.%03d.dynamic', $round),
            'options' => [
                'normal_writes_before' => $round % 4,
                'exclusive_writes' => 1 + ($round % 5),
                'normal_writes_after' => 1 + ($round % 4),
                'page_size' => $pageSize,
                'cache_pages' => $cachePages,
                'row_count' => $rowCount,
            ],
        ],
    ];

    foreach ($matrix as $entry) {
        ++$case;
        $tests[sprintf(
            'real upstream corpus vfs io dynamic exclusive locking %04d %s',
            $case,
            $entry['scenario']
        )] = static function (TestRunner $t) use ($entry, $expectedCounterSequence): void {
            $profile = SQLiteVfsIoDynamicPlan::exclusiveLockingProfile($entry['scenario'], $entry['options']);
            $group = $entry['group'];
            $options = $entry['options'];

            $t->same('ok', $profile['status']);
            $t->same($group, $profile['group']);
            $t->same($entry['scenario'], $profile['scenario']);
            $t->same(str_starts_with($group, 'exclusive2-') ? 'exclusive2.test' : 'exclusive.test', $profile['script']);
            $t->same($options['page_size'], $profile['page_size']);
            $t->same($options['cache_pages'], $profile['cache_pages']);
            $t->same($options['row_count'], $profile['row_count']);
            $t->same('exclusive', $profile['temp_locking_mode']);
            $t->same($options['cache_pages'] >= max(1, (int) ceil($options['row_count'] / 4)), $profile['pager_cache_can_hold_database']);
            $t->same(true, in_array('sqlite-upstream-exclusive-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-exclusive-locking', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
            $t->same(true, str_starts_with($profile['upstream'][0], $profile['script'] . ' '));

            if ($group === 'exclusive-1') {
                $assignment = $options['assignment'];
                $t->same($assignment, $profile['pragma_assignment']);
                $t->same($assignment === 'invalid', $profile['invalid_assignment_ignored']);
                $t->same($assignment === 'exclusive' ? 'exclusive' : 'normal', $profile['main_locking_mode']);
                $t->same('exclusive', $profile['temp_locking_mode_after_assignment']);
                $t->same($options['attached_databases'], $profile['attached_database_count']);
                $t->same(array_fill(0, $options['attached_databases'], $assignment === 'normal' ? 'normal' : 'exclusive'), $profile['attached_locking_modes']);
                $t->same($assignment !== 'invalid', $profile['mode_propagates_to_new_attaches']);
                $t->same('exclusive_pragma_sets_default_mode_while_temp_remains_exclusive', $profile['reason']);
            } elseif ($group === 'exclusive-2') {
                $stage = $options['stage'];
                $t->same($stage, $profile['stage']);
                $t->same(str_starts_with($stage, 'normal-') ? 'normal' : 'exclusive', $profile['locking_mode']);
                $t->same(in_array($stage, ['exclusive-shared-blocks-peer-write', 'peer-reserved-commit-blocked'], true) ? 'database is locked' : 'ok', $profile['peer_write_result']);
                $t->same(in_array($stage, ['exclusive-write-blocks-peer-read', 'normal-assignment-keeps-lock'], true) ? 'database is locked' : 'ok', $profile['peer_read_result']);
                $t->same($stage === 'peer-reserved-commit-blocked' ? 'database is locked' : 'ok', $profile['peer_commit_result']);
                $t->same($stage === 'normal-read-releases-lock', $profile['lock_released']);
                $t->same(false, $profile['normal_assignment_releases_immediately']);
            } elseif ($group === 'exclusive-3') {
                $event = $options['event'];
                $t->same($event, $profile['journal_event']);
                $t->same(in_array($event, ['begin-delete', 'commit', 'rollback'], true), $profile['journal_exists']);
                $t->same($event === 'begin-delete', $profile['journal_has_content']);
                $t->same($event === 'commit', $profile['commit_uses_truncate_not_delete']);
                $t->same($event === 'rollback', $profile['rollback_uses_truncate_not_delete']);
                $t->same($event === 'normal-release', $profile['normal_mode_access_deletes_truncated_journal']);
                $t->same($profile['journal_exists'], $profile['journal_file_state']['exists']);
                $t->same($profile['journal_has_content'], $profile['journal_file_state']['content']);
            } elseif ($group === 'exclusive-4') {
                $expectedSignature = 'count:' . $options['row_count'] . ':stable-md5';
                $t->same('exclusive', $profile['locking_mode']);
                $t->same($options['default_cache_size'], $profile['default_cache_size']);
                $t->same($options['mutation_rounds'], $profile['mutation_rounds']);
                $t->same($expectedSignature, $profile['signature_before']);
                $t->same($expectedSignature, $profile['signature_after_rollback']);
                $t->same(true, $profile['rollback_restores_signature']);
                $t->same(true, $profile['commit_after_rollback_allowed']);
            } elseif ($group === 'exclusive-5') {
                $event = $options['event'];
                $expectedOpenFiles = [
                    'normal-before-commit' => 2,
                    'normal-after-commit' => 1,
                    'exclusive-begin' => 2,
                    'exclusive-statement' => 2,
                    'exclusive-after-commit' => 2,
                    'normal-release' => 1,
                ][$event];
                $t->same($event, $profile['statement_event']);
                $t->same($expectedOpenFiles, $profile['open_file_count']);
                $t->same(in_array($event, ['exclusive-begin', 'exclusive-statement', 'exclusive-after-commit'], true), $profile['journal_handle_retained']);
                $t->same(true, $profile['statement_journal_opened_lazily']);
                $t->same($event === 'exclusive-after-commit', $profile['statement_journal_retained_after_commit']);
                $t->same($event === 'normal-release', $profile['normal_release_closes_extra_handles']);
            } elseif ($group === 'exclusive-6') {
                $hotCase = $options['case'];
                $t->same($hotCase, $profile['hot_journal_case']);
                $t->same($hotCase === 'copied-hot-journal', $profile['hot_journal_recovered']);
                $t->same($hotCase === 'empty-database-with-stray-journal', $profile['stray_journal_ignored_for_empty_database']);
                $t->same($hotCase === 'copied-hot-journal' ? ['exclusive', 'Eden', 1955] : ['exclusive'], $profile['select_result']);
                $t->same(true, str_starts_with($profile['reason'], 'exclusive_mode_'));
            } elseif ($group === 'exclusive-7') {
                $t->same(['exclusive', 'wal', 'normal', 0, 'delete'], $profile['pragma_sequence']);
                $t->same(true, $profile['wal_mode_entered_under_exclusive_lock']);
                $t->same(true, $profile['normal_mode_user_version_read_preserves_change_count_done']);
                $t->same(true, $profile['rollback_journal_mode_restored']);
            } elseif ($group === 'exclusive2-1') {
                $initial = $options['initial_change_counter'];
                $t->same('normal', $profile['locking_mode']);
                $t->same($initial, $profile['initial_change_counter']);
                $t->same($initial + 1, $profile['peer_update_change_counter']);
                $t->same($initial, $profile['reset_change_counter']);
                $t->same($initial + 1, $profile['incremented_change_counter']);
                $t->same(true, $profile['stale_cache_visible_before_counter_increment']);
                $t->same(true, $profile['database_change_visible_after_counter_increment']);
                $t->same(true, $profile['cache_uses_change_counter']);
            } elseif ($group === 'exclusive2-2') {
                $t->same('exclusive', $profile['locking_mode']);
                $t->same($options['corrupt_bytes'], $profile['corrupt_bytes']);
                $t->same(false, $profile['corruption_visible_while_exclusive']);
                $t->same(false, $profile['change_counter_checked_while_exclusive']);
                $t->same(true, $profile['normal_assignment_keeps_cache']);
                $t->same(true, $profile['corruption_visible_after_normal_unlock']);
                $t->same('database disk image is malformed', $profile['final_result']);
            } elseif ($group === 'exclusive2-3') {
                $t->same('exclusive-to-normal', $profile['locking_mode']);
                $t->same($options['normal_writes_before'], $profile['normal_writes_before']);
                $t->same($options['exclusive_writes'], $profile['exclusive_writes']);
                $t->same($options['normal_writes_after'], $profile['normal_writes_after']);
                $t->same($expectedCounterSequence($options['normal_writes_before'], $options['exclusive_writes'], $options['normal_writes_after']), $profile['change_counter_sequence']);
                $t->same(true, $profile['exclusive_reuses_change_counter']);
                $t->same(true, $profile['first_normal_write_after_release_reuses_counter']);
                $t->same($options['normal_writes_after'] > 1, $profile['subsequent_normal_write_increments_counter']);
            } else {
                $t->same('known exclusive group', $group);
            }
        };
    }
}

$tests['real upstream corpus vfs io dynamic exclusive locking owns twelve hundred cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1200, $case);
};

$tests['real upstream corpus vfs io dynamic exclusive locking cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $sections = [
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-2.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-3.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-4.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-5.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-6.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-7.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-1.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-2.citation')['upstream'][0],
        SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-3.citation')['upstream'][0],
    ];

    $t->same(true, in_array('exclusive.test exclusive-1.0 through exclusive-1.13 pragma locking_mode propagation', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-2.0 through exclusive-2.11 exclusive locks block peer reads/writes until normal access releases them', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-3.0 through exclusive-3.6 exclusive commits truncate rollback journal then normal access deletes it', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-4.0 through exclusive-4.5 rollback in exclusive mode preserves table signature', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-5.0 through exclusive-5.7 statement journal handles remain open in exclusive mode', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-6.2 through exclusive-6.5 exclusive mode opens copied hot-journal and stray-journal databases', $sections, true));
    $t->same(true, in_array('exclusive.test exclusive-7.1 WAL mode transition out of exclusive locking preserves Pager.changeCountDone state', $sections, true));
    $t->same(true, in_array('exclusive2.test exclusive2-1.0 through exclusive2-1.11 normal mode checks change-counter before discarding pager cache', $sections, true));
    $t->same(true, in_array('exclusive2.test exclusive2-2.1 through exclusive2-2.8 exclusive mode ignores on-disk corruption until normal unlock', $sections, true));
    $t->same(true, in_array('exclusive2.test exclusive2-3.0 through exclusive2-3.6 exclusive mode increments change-counter only once', $sections, true));
};

$tests['real upstream corpus vfs io dynamic exclusive locking rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-9.1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.bad', ['page_size' => 1000]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.bad', ['cache_pages' => 0]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.bad', ['row_count' => 0]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.bad', ['assignment' => 'shared']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-1.bad', ['attached_databases' => 5]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-2.bad', ['stage' => 'missing']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-3.bad', ['event' => 'delete']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-4.bad', ['mutation_rounds' => 0]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-5.bad', ['event' => 'missing']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive-6.bad', ['case' => 'missing']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-1.bad', ['initial_change_counter' => -1]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-2.bad', ['corrupt_bytes' => 0]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::exclusiveLockingProfile('exclusive2-3.bad', ['exclusive_writes' => 0]));
};

return $tests;
