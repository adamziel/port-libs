# Release Runner Upstream Burnup Current Next33

Slice: `yield-sqlite-release-runner-upstream-burnup-accepted-head`

Implemented `SQLiteUpstreamSuiteEvidence::releaseRunnerAcceptedHeadBurnup()` to classify release/all upstream burnup across an accepted-HEAD sequence. It counts only bounded runner artifacts that pass the existing accepted-head provenance gate: zero parsed errors, matching repository HEAD, matching SQLite manifest UUID, matching SQLite commit/version, and no active duplicate broad runner.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamBurnupTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 362 assertions, 0 failures
```

The focused run adds 60 verified `TestRunner` PASS lines. `lane-status.json` `phpPass` moved from `11206` to `11266`. `benchmarkDenominator.mapped` is intentionally unchanged because this is a runner countability/blocker classifier, not a newly mapped upstream behavior unit.

Non-overlap:

This avoids batch23 guarded runner countability preflight, release runner artifact directory evidence, upstream expression evidence, release-runner hydration cluster, accepted-head current-to-next map, and ordinary SQLite behavior clusters. The new surface is the multi-head burnup classifier that preserves counted artifacts while routing only the next missing accepted HEAD to a guarded runner when hydration, command-manifest, and active-runner gates are clear.

Dependency closure:

No new support component is needed. The helper composes existing lane-local manifest data, bounded-runner artifact parsing, accepted-head provenance checks, hydration gates, command manifests, and caller-supplied process snapshots. It does not inspect secrets, mutate upstream caches, or launch upstream runners.

Next:

Use this record to decide whether the next accepted HEAD has enough provenance to count release/all burnup, whether to launch at most one guarded runner, or whether to preserve existing artifacts and resolve hydration/command/duplicate-runner blockers first.
