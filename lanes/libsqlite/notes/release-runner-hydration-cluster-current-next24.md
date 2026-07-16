# Release Runner Hydration Cluster Current Next24

- Source base: `2526c99030a288ad50fc53257065420d1dcd6b85`.
- Focused delta: `+49` TestRunner PASS lines, from `8166` to `8215` lane-local `phpPass`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerHydrationClusterCurrentNext24Test.php`.
- Verified output: `1 test files, 173 assertions, 0 failures`.
- Root harness: not run; isolated micro-slice.

## Behavior

Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerHydrationClusterRecord()` as a single current/next release-runner admission record. It composes existing lane-local gates for upstream runner hydration, release-tier readiness, selected-script inventory, duplicate broad-runner suppression, and accepted-HEAD audit/log artifact provenance.

The record is countable in three useful states:

- `blocked` when cache inputs, selected scripts, release tiers, duplicate-runner state, or artifact provenance are incomplete.
- `ready-for-guarded-runner` when the upstream cache and suite commands are hydrated, no duplicate broad runner is active, and no accepted-HEAD artifact is present yet.
- `current-accepted-artifact-ready` when an accepted-HEAD audit/log artifact is already discoverable, suppressing another broad runner launch.

## Non-Overlap

This avoids accepted batch21 release countability gaps and runner artifact directory evidence by not reshaping the existing ledger or claiming a broad-suite pass. It also avoids the accepted SQL, JSON table, VFS, WAL, B-tree, Unicode GLOB, rollback-journal, and import transaction behavior clusters; the only new surface is the hydrated current/next release-runner gate composition.

## Dependency Closure

No new support component is needed. The helper reads lane-local manifest data, caller-supplied process snapshots, and guarded audit/log artifact files only; it does not inspect secrets, mutate upstream caches, or launch upstream runners.
