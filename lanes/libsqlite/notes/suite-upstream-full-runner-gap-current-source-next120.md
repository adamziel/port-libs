# suite-upstream-full-runner-gap-current-source-next120

## Behavior

`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()` now admits guarded
SQLite runner artifacts whose stdout/audit has zero-error pass summaries in
forms commonly emitted by Tcl runner wrappers:

- `All N tests passed`
- `N tests passed, 0 errors`

This removes a current-source countability blocker where a bounded runner could
finish cleanly but remain `running-or-incomplete` because the audit lacked the
older `Parsed tests` or `0 errors out of N tests` wording.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamRunnerStdoutPassSummaryCurrentSourceNext120Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteUpstreamRunnerStdoutPassSummaryCurrentSourceNext120Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerStdoutPassSummaryCurrentSourceNext120Test.php`
  - `1 test files, 485 assertions, 0 failures`
  - `58` PASS lines

## Non-Overlap

This slice does not repeat accepted full-suite countability next116, release
admission next114, release gap burnup next117, runner106 suite-evidence rebase,
or behavior clusters in SQL, JSON, WAL, VFS, pager, B-tree, encoding, trigger,
or PRAGMA work. It only broadens bounded runner artifact parsing for already
completed zero-error stdout summaries.

## Dependency Closure

No new support component is needed. The patch reuses the existing lane-local
bounded runner artifact and provenance gates.
