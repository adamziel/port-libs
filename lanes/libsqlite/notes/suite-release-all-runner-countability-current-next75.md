# SQLite Suite Release/All Runner Countability Current-Next75

## Scope

This slice removes one suite-family countability blocker without launching a
broad upstream runner. It adds a lane-local validator for accepted-HEAD
release/all runner artifacts:

- accepted repository head must match `c196709c053869bec78f15d5a1f299d396f8fdb0`
- artifact paths must stay under `lanes/libsqlite/`
- runner commands must invoke `testfixture ... testrunner.tcl` with `all` or
  `release`
- counted artifacts must have exit `0`, errors `0`, positive test counts, and
  concrete `.test` or wildcard script selections
- duplicate broad runner snapshots block counting
- release/all parity remains false until a separate broad zero-error closure
  artifact is accepted

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityCurrentNext75Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 871 assertions, 0 failures
```

The focused run emitted 89 `PASS` lines, so `lane-status.json` moves
`phpPass` from `28917` to `29006`. The manifest mapped denominator moves
`464 -> 465 / 1589` for this one countability blocker row.

## Non-Overlap

This does not duplicate accepted current-next72 release-runner admission,
current-next70 release shard countability, suite-denominator freshness,
accepted-head suite provenance, or any batch70/71 behavior clusters. It is a
suite/countability gate only and adds no SQL, JSON, WAL, pager, VFS, B-tree, or
WordPress behavior helper.

## Dependency Closure

No new support component is needed. The validator composes lane-local artifact
metadata, guarded runner command strings, active-runner snapshots, and focused
PHP TestRunner output only.
