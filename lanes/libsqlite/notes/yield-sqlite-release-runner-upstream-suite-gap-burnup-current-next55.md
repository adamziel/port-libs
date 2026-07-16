# Release Runner Upstream Suite Gap Burnup Current Next55

## Scope

- Added `SQLiteUpstreamSuiteEvidence::releaseRunnerUpstreamSuiteGapBurnupCurrentNext55()` for guarded artifact-directory current/next suite gap burnup.
- The helper compares current and next accepted-HEAD artifact directories by suite key, counts only zero-error artifacts with matching accepted repository HEAD and SQLite manifest UUID, and keeps stale, failed, missing, active, regressed, and under-threshold focused PHP evidence explicit.
- This does not launch a broad upstream runner and does not claim release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteGapBurnupCurrentNext55Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
64 PASS lines
1 test files, 409 assertions, 0 failures
```

## Status Delta

- `lane-status.json` `phpPass`: `20008 -> 20072` from the 64 verified PASS lines above.
- `benchmarkDenominator.mapped`: unchanged; this slice adds countability evidence logic and focused PHP coverage only.

## Non-Overlap

This slice avoids accepted current-next50 burnup, artifact-directory countability, release blocker closure, selected-script gap proof, and all accepted VFS/WAL/B-tree/JSON/SQL behavior clusters.

## Dependency Closure

No new support component is needed. The helper composes existing guarded bounded-runner artifact parsing, accepted-HEAD provenance checks, and focused PHP TestRunner admission only.
