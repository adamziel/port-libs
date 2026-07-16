<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_json_summary57_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_json_summary57_audit(string $head = 'accepted-head', string $summary = ''): string
{
    $summary = $summary === '' ? json_encode([
        'exit' => 0,
        'elapsed_seconds' => 188,
        'errors' => 0,
        'tests' => 24017,
        'runner_time' => '00:03:08',
        'jobs' => 2,
        'timeout_seconds' => 7200,
        'testset' => 'release',
        'patterns' => ['json101.test', 'wal*.test'],
    ], JSON_THROW_ON_ERROR) : $summary;

    return <<<MD
# SQLite Tcl Bounded Runner Evidence - json-summary-next57

- Repository HEAD: `{$head}`
- Scratch: `/tmp/libsqlite-json-summary-next57`
- Log: `/tmp/libsqlite-json-summary-next57.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Summary JSON: `{$summary}`
MD;
}

$record = libsqlite_json_summary57_evidence()->boundedRunnerArtifactRecord(libsqlite_json_summary57_audit());
$gate = libsqlite_json_summary57_evidence()->boundedRunnerCountabilityGateFromRecord($record, 'accepted-head');
$failed = libsqlite_json_summary57_evidence()->boundedRunnerArtifactRecord(libsqlite_json_summary57_audit('accepted-head', json_encode([
    'exit_code' => 1,
    'parsed_errors' => 3,
    'parsed_tests' => 1024,
    'suite' => 'all',
], JSON_THROW_ON_ERROR)));
$fenced = libsqlite_json_summary57_evidence()->boundedRunnerArtifactRecord(str_replace(
    '- Summary JSON: `' . json_encode([
        'exit' => 0,
        'elapsed_seconds' => 188,
        'errors' => 0,
        'tests' => 24017,
        'runner_time' => '00:03:08',
        'jobs' => 2,
        'timeout_seconds' => 7200,
        'testset' => 'release',
        'patterns' => ['json101.test', 'wal*.test'],
    ], JSON_THROW_ON_ERROR) . '`',
    "```json\n{\"exit\":0,\"errors\":0,\"tests\":17,\"testset\":\"veryquick\"}\n```",
    libsqlite_json_summary57_audit()
));
$envLine = libsqlite_json_summary57_evidence()->boundedRunnerArtifactRecord(
    <<<'MD'
# SQLite Tcl Bounded Runner Evidence - json-summary-next57

- Repository HEAD: `accepted-head`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
RUNNER_SUMMARY_JSON={"exit":0,"errors":0,"tests":19,"jobs":"1"}
MD
);

$cases = [
    'json summary marks artifact passed' => static fn (TestRunner $t): mixed => $t->same('passed', $record['status']),
    'json summary keeps repository head' => static fn (TestRunner $t): mixed => $t->same('accepted-head', $record['repository_head']),
    'json summary parses release testset' => static fn (TestRunner $t): mixed => $t->same('release', $record['requested']['testset']),
    'json summary parses jobs' => static fn (TestRunner $t): mixed => $t->same(2, $record['requested']['jobs']),
    'json summary parses timeout' => static fn (TestRunner $t): mixed => $t->same(7200, $record['requested']['timeout_seconds']),
    'json summary parses patterns' => static fn (TestRunner $t): mixed => $t->same(['json101.test', 'wal*.test'], $record['requested']['patterns']),
    'json summary parses exit' => static fn (TestRunner $t): mixed => $t->same(0, $record['results']['exit']),
    'json summary parses elapsed seconds' => static fn (TestRunner $t): mixed => $t->same(188, $record['results']['elapsed_seconds']),
    'json summary parses tests' => static fn (TestRunner $t): mixed => $t->same(24017, $record['results']['tests']),
    'json summary parses errors' => static fn (TestRunner $t): mixed => $t->same(0, $record['results']['errors']),
    'json summary parses runner time' => static fn (TestRunner $t): mixed => $t->same('00:03:08', $record['results']['runner_time']),
    'json summary has no failure blockers' => static fn (TestRunner $t): mixed => $t->same([], $record['results']['failure_blockers']),
    'json summary active gate is clear' => static fn (TestRunner $t): mixed => $t->same('clear', $record['active_gate']['status']),
    'json summary countability is countable' => static fn (TestRunner $t): mixed => $t->same('countable', $gate['status']),
    'json summary countability boolean true' => static fn (TestRunner $t): mixed => $t->same(true, $gate['countable']),
    'json summary acceptance is accepted' => static fn (TestRunner $t): mixed => $t->same('accepted-for-lane-evidence', $gate['acceptance']['status']),
    'json summary acceptance tests preserved' => static fn (TestRunner $t): mixed => $t->same(24017, $gate['acceptance']['tests']),
    'json summary acceptance errors preserved' => static fn (TestRunner $t): mixed => $t->same(0, $gate['acceptance']['errors']),
    'json summary acceptance exit preserved' => static fn (TestRunner $t): mixed => $t->same(0, $gate['acceptance']['exit']),
    'json summary has no countability blockers' => static fn (TestRunner $t): mixed => $t->same(0, $gate['blocker_count']),
    'json summary next gate records evidence' => static fn (TestRunner $t): mixed => $t->contains('record this bounded runner artifact', $gate['next_gate']),
    'json summary dependency closure is explicit' => static fn (TestRunner $t): mixed => $t->contains('no new support component needed', $gate['dependency_closure']),
    'failed json summary marks artifact failed' => static fn (TestRunner $t): mixed => $t->same('failed', $failed['status']),
    'failed json summary parses alternate exit key' => static fn (TestRunner $t): mixed => $t->same(1, $failed['results']['exit']),
    'failed json summary parses alternate error key' => static fn (TestRunner $t): mixed => $t->same(3, $failed['results']['errors']),
    'failed json summary parses alternate test key' => static fn (TestRunner $t): mixed => $t->same(1024, $failed['results']['tests']),
    'failed json summary parses suite alias' => static fn (TestRunner $t): mixed => $t->same('all', $failed['requested']['testset']),
    'fenced json summary marks artifact passed' => static fn (TestRunner $t): mixed => $t->same('passed', $fenced['status']),
    'fenced json summary parses tests' => static fn (TestRunner $t): mixed => $t->same(17, $fenced['results']['tests']),
    'fenced json summary parses errors' => static fn (TestRunner $t): mixed => $t->same(0, $fenced['results']['errors']),
    'fenced json summary parses testset' => static fn (TestRunner $t): mixed => $t->same('veryquick', $fenced['requested']['testset']),
    'env line json summary marks artifact passed' => static fn (TestRunner $t): mixed => $t->same('passed', $envLine['status']),
    'env line json summary parses tests' => static fn (TestRunner $t): mixed => $t->same(19, $envLine['results']['tests']),
    'env line json summary parses string jobs' => static fn (TestRunner $t): mixed => $t->same(1, $envLine['requested']['jobs']),
    'missing json summary fields stay null' => static fn (TestRunner $t): mixed => $t->same(null, $envLine['requested']['timeout_seconds']),
    'json summary keeps manifest uuid' => static fn (TestRunner $t): mixed => $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $record['sqlite_manifest_uuid']),
    'json summary keeps sqlite commit' => static fn (TestRunner $t): mixed => $t->same('8f70ec615f4cd247d36f92a22c99f65ebbcc22a7', $record['sqlite_commit']),
    'json summary keeps sqlite version' => static fn (TestRunner $t): mixed => $t->same('3.54.0', $record['sqlite_version']),
    'json summary preserves label' => static fn (TestRunner $t): mixed => $t->same('json-summary-next57', $record['label']),
    'json summary progress remains parseable without stdout' => static fn (TestRunner $t): mixed => $t->same(null, $record['progress']['completed']),
    'json summary failure count is zero' => static fn (TestRunner $t): mixed => $t->same(0, $record['results']['failure_count']),
    'json summary blocked mismatch still rejects stale head' => static function (TestRunner $t): void {
        $stale = libsqlite_json_summary57_evidence()->boundedRunnerCountabilityGateFromRecord(
            libsqlite_json_summary57_evidence()->boundedRunnerArtifactRecord(libsqlite_json_summary57_audit('stale-head')),
            'accepted-head'
        );
        $t->same('blocked', $stale['status']);
        $t->same(['repository-head-mismatch'], array_column($stale['blockers'], 'id'));
    },
];

return $cases;
