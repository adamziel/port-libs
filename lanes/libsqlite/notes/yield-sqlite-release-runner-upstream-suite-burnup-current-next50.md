# Release Runner Upstream Suite Burnup Current Next50

## Scope

- Added `SQLiteUpstreamSuiteEvidence::releaseRunnerUpstreamSuiteBurnup()` for lane-local current/next accepted-source suite burnup evidence.
- The helper classifies suite rows by tier, artifact label, current/next countability, test-count movement, blockers, and focused PHP admission.
- This does not launch a broad upstream runner and does not claim release/all parity. It keeps open, regressed, under-threshold, and invalid rows uncounted.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteBurnupTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
64 PASS lines
1 test files, 435 assertions, 0 failures
```

## Non-Overlap

This slice avoids accepted batch48 suite progress, canonical artifact maps, selected-script gap proof, VFS/WAL/B-tree/JSON/SQL behavior clusters, and does not duplicate accepted release-runner artifact directory evidence.

## Dependency Closure

No new support component is needed. The burnup helper composes lane-local suite row metadata and focused TestRunner output only.
