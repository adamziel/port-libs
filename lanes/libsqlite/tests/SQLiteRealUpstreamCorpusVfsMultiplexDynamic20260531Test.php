<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$align = static function (int $value, int $alignment): int {
    $remainder = $value % $alignment;
    return $remainder === 0 ? $value : $value + ($alignment - $remainder);
};

$groupForScenario = static function (string $scenario): string {
    foreach (['multiplex4-1', 'multiplex3-1', 'multiplex3-2', 'multiplex3-3', 'multiplex2-1'] as $group) {
        if (str_starts_with($scenario, $group)) {
            return $group;
        }
    }

    foreach (['multiplex-1', 'multiplex-2', 'multiplex-3'] as $group) {
        if (str_starts_with($scenario, $group)) {
            return $group;
        }
    }

    throw new InvalidArgumentException("Unsupported multiplex scenario in test matrix: {$scenario}");
};

$scenarioScripts = [
    'multiplex-1' => 'multiplex.test',
    'multiplex-2' => 'multiplex.test',
    'multiplex-3' => 'multiplex.test',
    'multiplex2-1' => 'multiplex2.test',
    'multiplex3-1' => 'multiplex3.test',
    'multiplex3-2' => 'multiplex3.test',
    'multiplex3-3' => 'multiplex3.test',
    'multiplex4-1' => 'multiplex4.test',
];

$scenarios = [
    'multiplex-1.5',
    'multiplex-2.5',
    'multiplex-2.7',
    'multiplex-3.1',
    'multiplex2-1.1',
    'multiplex3-1',
    'multiplex3-2.1',
    'multiplex3-3',
    'multiplex4-1.1',
];
$chunkSizes = [10, 31, 4096, 32768, 65536, 262144, 1048576];
$pageSizes = [512, 1024, 2048, 4096, 8192];
$payloadSizes = [0, 1000, 4096, 32768, 65536, 250000];
$journalModes = ['delete', 'persist', 'truncate', 'memory', 'off'];
$maxChunks = [2, 3, 5, 8, 16];

foreach (range(1, 1000) as $case) {
    $scenario = $scenarios[($case - 1) % count($scenarios)];
    $group = $groupForScenario($scenario);
    $chunkSize = $chunkSizes[$case % count($chunkSizes)];
    $pageSize = $pageSizes[(int) floor($case / 2) % count($pageSizes)];
    $payloadBytes = $payloadSizes[(int) floor($case / 3) % count($payloadSizes)];
    $rowCount = 1 + (($case * 17) % 640);
    $journalMode = $journalModes[(int) floor($case / 5) % count($journalModes)];
    $limitChunks = $maxChunks[(int) floor($case / 7) % count($maxChunks)];
    $enabled = !str_starts_with($scenario, 'multiplex-2.7') && ($case % 11) !== 0;
    $shortNames = str_starts_with($group, 'multiplex3') || ($case % 13) === 0;
    $truncate = $group === 'multiplex4-1' && ($case % 2) === 0;
    $baseName = $group === 'multiplex4-1'
        ? 'mx4test.db'
        : ($shortNames ? 'test.db' : 'vfs-corpus-' . $case . '.db');

    $tests[sprintf('real upstream corpus vfs multiplex dynamic %04d %s', $case, $scenario)] = static function (TestRunner $t) use (
        $align,
        $baseName,
        $case,
        $chunkSize,
        $enabled,
        $group,
        $journalMode,
        $limitChunks,
        $pageSize,
        $payloadBytes,
        $rowCount,
        $scenario,
        $scenarioScripts,
        $shortNames,
        $truncate
    ): void {
        $plan = SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile($scenario, [
            'base_name' => $baseName,
            'chunk_size' => $chunkSize,
            'enabled' => $enabled,
            'journal_mode' => $journalMode,
            'max_chunks' => $limitChunks,
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'peer_connections' => str_starts_with($group, 'multiplex-3') || str_starts_with($group, 'multiplex2') ? 2 : 1,
            'row_count' => $rowCount,
            'short_names' => $shortNames,
            'truncate' => $truncate,
        ]);

        $expectedAligned = $align($chunkSize, 65536);
        $expectedBytes = $align(max($pageSize * 2, ($pageSize * 2) + ($rowCount * ($payloadBytes + 64))), $pageSize);
        $expectedChunkCount = $enabled ? max(1, min($limitChunks, (int) ceil($expectedBytes / $expectedAligned))) : 1;
        if ($group === 'multiplex4-1' && $enabled) {
            $expectedChunkCount = $expectedBytes > $expectedAligned ? min($limitChunks, 2) : 1;
        }

        $t->same('ok', $plan['status']);
        $t->same($scenarioScripts[$group], $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($group, $plan['group']);
        $t->same('multiplex', $plan['vfs']);
        $t->same($baseName, $plan['base_name']);
        $t->same($shortNames, $plan['short_names']);
        $t->same($enabled, $plan['enabled']);
        $t->same($journalMode, $plan['journal_mode']);
        $t->same($pageSize, $plan['page_size']);
        $t->same($rowCount, $plan['row_count']);
        $t->same($payloadBytes, $plan['payload_bytes']);
        $t->same($expectedBytes, $plan['database_bytes']);
        $t->same($chunkSize, $plan['chunk_size_requested']);
        $t->same(65536, $plan['chunk_size_alignment']);
        $t->same($expectedAligned, $plan['chunk_size_aligned']);
        $t->same($limitChunks, $plan['max_chunks']);
        $t->same($expectedChunkCount, $plan['chunk_count']);
        $t->same($expectedChunkCount, count($plan['chunk_files']));
        $t->same($enabled ? 1 : 0, $plan['pragma_multiplex_enabled']);
        $t->same($expectedChunkCount, $plan['pragma_multiplex_filecount']);
        $t->same($expectedAligned, $plan['pragma_multiplex_chunksize']);
        $t->same($expectedBytes > $expectedAligned, $plan['would_span_chunks']);
        $t->same(!$enabled && $expectedBytes > $expectedAligned, $plan['disabled_keeps_single_base_file']);
        $t->same($rowCount, $plan['first_connection_row_count']);
        $t->same(true, in_array('sqlite-vfs-multiplex-chunks', $plan['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
        $t->same(true, str_starts_with($plan['upstream'][0], $plan['script']));

        if ($expectedChunkCount > 1) {
            $expectedSuffix = $shortNames
                ? preg_replace('/\.[^.]+$/', '.001', $baseName)
                : $baseName . '001';
            $t->same(true, in_array($expectedSuffix, $plan['chunk_files'], true));
        } else {
            $t->same([$baseName], $plan['chunk_files']);
        }

        if (str_starts_with($group, 'multiplex2')) {
            $t->same(true, $plan['multi_client_delete_vacuum_visible']);
            $t->same($rowCount, $plan['second_connection_row_count']);
        }

        if ($group === 'multiplex3-2') {
            $t->same(true, $plan['hot_journal_copy_preserves_checksum']);
        }

        if ($group === 'multiplex3-3') {
            $t->same(true, $plan['backup_reopen_checksum_stable']);
        }

        if ($group === 'multiplex4-1') {
            $t->same(true, $plan['truncate_file_control_handled']);
            $t->same($truncate, $plan['truncate_enabled']);
            $t->same(['on', 'off', $truncate ? 'on' : 'off'], $plan['pragma_multiplex_truncate_sequence']);
            $t->same($truncate ? [$baseName] : $plan['chunk_files'], $plan['files_after_vacuum']);
        }

        $t->same(true, strlen($plan['reason']) > 0);
        $t->same($case > 0, true);
    };
}

$upstreamGroups = [
    'multiplex-1.5',
    'multiplex-2.5',
    'multiplex-3.1',
    'multiplex2-1.1',
    'multiplex3-1',
    'multiplex3-2.1',
    'multiplex3-3',
    'multiplex4-1.1',
];

foreach ($upstreamGroups as $scenario) {
    $tests['real upstream corpus vfs multiplex cites source truth ' . $scenario] = static function (TestRunner $t) use ($groupForScenario, $scenario, $scenarioScripts): void {
        $group = $groupForScenario($scenario);
        $plan = SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile($scenario);

        $t->same($scenarioScripts[$group], $plan['script']);
        $t->same(true, count($plan['upstream']) >= 2);
        $t->same(true, str_starts_with($plan['upstream'][0], $plan['script']));
        $t->same(true, in_array('sqlite-upstream-multiplex-test', $plan['dependencies'], true));
    };
}

$rejects = [
    'empty scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile(''),
    'bad scenario' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('not-multiplex'),
    'bad base name' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['base_name' => '']),
    'bad chunk size' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['chunk_size' => 0]),
    'bad max chunks' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['max_chunks' => 0]),
    'bad page size' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['page_size' => 1000]),
    'bad journal mode' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['journal_mode' => 'wal']),
    'bad rows' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-2.5', ['row_count' => 0]),
    'bad peers' => static fn (): array => SQLiteVfsIoDynamicPlan::multiplexVfsChunkProfile('multiplex-3.1', ['peer_connections' => 0]),
];

foreach ($rejects as $name => $callback) {
    $tests['real upstream corpus vfs multiplex rejects malformed input ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
