# SQLite release-runner suite gap burn-up current-next51

## Scope

- Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteGapBurnup()`.
- Adds focused coverage in `SQLiteReleaseRunnerSuiteGapBurnupTest.php`.
- Tracks current/next suite burn-up from existing suite progress rows without launching a broad runner.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteGapBurnupTest.php
Focused test run: 1 selected test files (root lock skipped)
65 PASS lines
1 test files, 525 assertions, 0 failures
```

Dashboard movement:

- `phpPass`: `18565` -> `18630` (`+65` verified focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this slice records countability/burn-up evidence and does not map new upstream inventory units.

## Non-overlap

This avoids accepted release-runner canonical map, current-next48 suite progress map, guarded artifact directory evidence, upstream expression evidence, and the JSON/VFS/WAL/B-tree/SQL behavior clusters. It only composes lane-local suite rows, focused PHP admission, tier burn-up, and explicit remaining release/all blockers.

## Dependency closure

No new support component is needed. The method reuses existing suite progress row metadata and focused PHP TestRunner admission.
