# Release Runner Artifact Directory Next5

## Scope

This slice removes a bounded release-runner handoff blocker: guarded SQLite Tcl
runner artifacts can now be scanned from a directory, paired with their stdout
logs, and routed through the existing accepted-HEAD provenance/countability
gates without launching another broad upstream suite.

The slice intentionally does not repeat `releaseBlockerClosureRecord()`,
fts5aux sanitizer classification, focused runner admission, broad runner
launch gating, or release/all ledger policy. It only discovers audit/log pairs
and keeps missing logs, stale heads, active runners, and countable zero-error
artifacts explicit.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactDirectoryTest.php`
  - `1 test files, 39 assertions, 0 failures`
  - New PASS-line delta: `+5`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactDirectoryTest.php`
  - `2 test files, 851 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

## Dashboard Delta

- `phpPass`: `1684 -> 1689`
- `benchmarkDenominator.mapped`: unchanged; no newly mapped upstream inventory
  unit is claimed.

## Dependency Closure

No new support component is needed. The helper reads bounded runner audit/log
artifacts and reuses lane-local provenance/countability gates.
