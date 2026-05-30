# SQLite Suite Release/All Runner Countability

## Scope

This consolidation keeps the accepted release/all countability behavior while
removing the numbered production helper name. The lane-local validator still
checks accepted-HEAD release/all runner artifacts:

- accepted repository head must match `c196709c053869bec78f15d5a1f299d396f8fdb0`
- artifact paths must stay under `lanes/libsqlite/`
- runner commands must invoke `testfixture ... testrunner.tcl` with `all` or
  `release`
- counted artifacts must have exit `0`, errors `0`, positive test counts, and
  concrete `.test` or wildcard script selections
- duplicate broad runner snapshots block counting
- release/all parity remains false until a separate broad zero-error closure
  artifact is accepted

## Current Consolidation Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 871 assertions, 0 failures
```

This cleanup does not claim new `phpPass` or mapped-denominator movement. It
renames the production helper and direct focused test/note path while preserving
the existing current-next75 receipt/status strings and release/all assertions.

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
