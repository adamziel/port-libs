<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

$evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

$artifact = static function (
    string $label,
    string $testset,
    string $status,
    ?int $completed,
    ?int $total,
    ?int $tests,
    ?int $errors,
    array $patterns = []
) use ($evidence): array {
    $audit = <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `progress-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `{$testset}`
MD;

    if ($patterns !== []) {
        $audit .= "\n- Patterns: `" . implode('` `', $patterns) . '`';
    }
    if ($status === 'passed') {
        $audit .= "\n- Exit: `0`\n- Parsed errors: `0`\n- Parsed tests: `" . (string) $tests . "`\n";
    } elseif ($status === 'failed') {
        $audit .= "\n- Exit: `1`\n- Parsed errors: `" . (string) $errors . "`\n- Parsed tests: `" . (string) $tests . "`\n";
    } elseif ($status === 'timed-out-incomplete') {
        $audit .= "\n- Exit: `124`\n";
    }

    $stdout = $completed === null || $total === null
        ? ''
        : sprintf("06:40 tcl(%d/%d) r2 ETC 00:12\n", $completed, $total);
    $snapshot = $status === 'active-runner-in-progress'
        ? '4321 4320 06:40 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error ' . $testset
        : '';

    return $evidence->boundedRunnerArtifactRecord($audit, $stdout, $snapshot);
};

$releasePassed = $artifact('release-pass', 'release', 'passed', 22000, 22000, 22000, 0);
$allFailed = $artifact('all-failed', 'all', 'failed', 10000, 22000, 10001, 1);
$releaseTimedOut = $artifact('release-timeout', 'release', 'timed-out-incomplete', 18142, 22000, null, null);
$releaseActive = $artifact('release-active', 'release', 'active-runner-in-progress', 3840, 22000, null, null);
$focusedPassed = $artifact('focused-json', 'veryquick', 'passed', 650, 650, 650, 0, ['json101.test', 'jsonb01.test']);
$blockedBeforeRun = $artifact('release-blocked-before-run', 'release', 'blocked-before-run', null, null, null, null);

$mixedAudit = $evidence->boundedRunnerProgressAudit([
    'release-pass' => $releasePassed,
    'all-failed' => $allFailed,
    'release-timeout' => $releaseTimedOut,
    'release-active' => $releaseActive,
    'focused-json' => $focusedPassed,
    'release-blocked-before-run' => $blockedBeforeRun,
]);

$tests = [];

$tests['bounded runner progress audit summarizes mixed artifact status counts'] = static function (TestRunner $t) use ($mixedAudit): void {
    $t->same('blocked-progress-only', $mixedAudit['status']);
    $t->same(6, $mixedAudit['artifact_count']);
    $t->same(2, $mixedAudit['passed_count']);
    $t->same(1, $mixedAudit['failed_count']);
    $t->same(1, $mixedAudit['active_count']);
    $t->same(1, $mixedAudit['timed_out_count']);
    $t->same(1, $mixedAudit['incomplete_count']);
};

$tests['bounded runner progress audit separates release like and focused artifacts'] = static function (TestRunner $t) use ($mixedAudit): void {
    $t->same(5, $mixedAudit['release_like_count']);
    $t->same(1, $mixedAudit['focused_count']);
    $t->same(['release-pass', 'all-failed', 'release-timeout', 'release-active', 'release-blocked-before-run'], $mixedAudit['release_like_labels']);
    $t->same(['focused-json'], $mixedAudit['focused_labels']);
    $t->same(false, $mixedAudit['counts_as_release_parity']);
};

$tests['bounded runner progress audit accumulates completed tcl progress only from parsed progress lines'] = static function (TestRunner $t) use ($mixedAudit): void {
    $t->same(54632, $mixedAudit['completed_progress_total']);
    $t->same(88650, $mixedAudit['planned_progress_total']);
    $t->same(100.0, $mixedAudit['max_progress_percent']);
    $t->same(1, $mixedAudit['errors_total']);
};

$tests['bounded runner progress audit accumulates passed and failed parsed test totals'] = static function (TestRunner $t) use ($mixedAudit): void {
    $t->same(32651, $mixedAudit['tests_total']);
    $t->same(1, $mixedAudit['errors_total']);
    $t->same(['release-pass', 'focused-json'], $mixedAudit['passed_labels']);
    $t->same(['all-failed'], $mixedAudit['failed_labels']);
};

$tests['bounded runner progress audit preserves active and timed out labels for rerun gating'] = static function (TestRunner $t) use ($mixedAudit): void {
    $t->same(['release-active'], $mixedAudit['active_labels']);
    $t->same(['release-timeout'], $mixedAudit['timed_out_labels']);
    $t->same(['release-blocked-before-run'], $mixedAudit['incomplete_labels']);
    $t->contains('keep release/all parity uncounted', $mixedAudit['next_gate']);
};

$entryChecks = [
    'release pass entry' => [0, 'release-pass', 'passed', 'release-like', 100.0, null],
    'all failed entry' => [1, 'all-failed', 'failed', 'release-like', 45.45, null],
    'release timeout entry' => [2, 'release-timeout', 'timed-out-incomplete', 'release-like', 82.46, '06:40 tcl(18142/22000) r2 ETC 00:12'],
    'release active entry' => [3, 'release-active', 'active-runner-in-progress', 'release-like', 17.45, '06:40 tcl(3840/22000) r2 ETC 00:12'],
    'focused entry' => [4, 'focused-json', 'passed', 'focused', 100.0, null],
    'blocked before run entry' => [5, 'release-blocked-before-run', 'blocked-before-run', 'release-like', null, null],
];

foreach ($entryChecks as $name => [$index, $label, $status, $kind, $percent, $lastLine]) {
    $tests['bounded runner progress audit preserves ' . $name] = static function (TestRunner $t) use ($mixedAudit, $index, $label, $status, $kind, $percent, $lastLine): void {
        $entry = $mixedAudit['entries'][$index];
        $t->same($label, $entry['label']);
        $t->same($status, $entry['status']);
        $t->same($kind, $entry['kind']);
        $t->same($percent, $entry['progress_percent']);
        if ($lastLine !== null) {
            $t->same($lastLine, $entry['last_progress_line']);
        }
    };
}

$tests['bounded runner progress audit keeps focused patterns with selected artifact'] = static function (TestRunner $t) use ($mixedAudit): void {
    $focused = $mixedAudit['entries'][4];
    $t->same('veryquick', $focused['testset']);
    $t->same(['json101.test', 'jsonb01.test'], $focused['patterns']);
    $t->same(650, $focused['tests']);
    $t->same(0, $focused['errors']);
};

$tests['bounded runner progress audit reports passed-only artifact batch without release parity credit'] = static function (TestRunner $t) use ($evidence, $releasePassed, $focusedPassed): void {
    $audit = $evidence->boundedRunnerProgressAudit([
        'release-pass' => $releasePassed,
        'focused-json' => $focusedPassed,
    ]);

    $t->same('passed-progress-recorded', $audit['status']);
    $t->same(2, $audit['passed_count']);
    $t->same(0, $audit['failed_count']);
    $t->same(false, $audit['counts_as_release_parity']);
    $t->contains('send passed artifacts through provenance', $audit['next_gate']);
};

$tests['bounded runner progress audit reports failed-only artifact batch distinctly'] = static function (TestRunner $t) use ($evidence, $allFailed): void {
    $audit = $evidence->boundedRunnerProgressAudit(['all-failed' => $allFailed]);

    $t->same('failed-progress-recorded', $audit['status']);
    $t->same(0, $audit['passed_count']);
    $t->same(1, $audit['failed_count']);
    $t->same(10001, $audit['tests_total']);
    $t->same(1, $audit['errors_total']);
};

$tests['bounded runner progress audit reports an empty artifact batch as blocked'] = static function (TestRunner $t) use ($evidence): void {
    $audit = $evidence->boundedRunnerProgressAudit([]);

    $t->same('blocked-no-artifacts', $audit['status']);
    $t->same(0, $audit['artifact_count']);
    $t->same(0, $audit['completed_progress_total']);
    $t->contains('keep release/all parity uncounted', $audit['next_gate']);
};

$tests['bounded runner progress audit ignores invalid non array entries'] = static function (TestRunner $t) use ($evidence, $releasePassed): void {
    $audit = $evidence->boundedRunnerProgressAudit([
        'invalid' => 'not an artifact',
        'release-pass' => $releasePassed,
    ]);

    $t->same(1, $audit['artifact_count']);
    $t->same(['release-pass'], $audit['passed_labels']);
    $t->same('passed-progress-recorded', $audit['status']);
};

for ($i = 1; $i <= 20; $i++) {
    $completed = $i * 10;
    $total = 200;
    $record = $artifact('release-progress-' . $i, 'release', 'timed-out-incomplete', $completed, $total, null, null);

    $tests['bounded runner progress audit computes timeout progress percent ' . $i] = static function (TestRunner $t) use ($evidence, $record, $completed, $total): void {
        $audit = $evidence->boundedRunnerProgressAudit(['progress' => $record]);
        $expected = round(($completed / $total) * 100, 2);

        $t->same('blocked-progress-only', $audit['status']);
        $t->same($completed, $audit['completed_progress_total']);
        $t->same($total, $audit['planned_progress_total']);
        $t->same($expected, $audit['entries'][0]['progress_percent']);
    };
}

return $tests;
