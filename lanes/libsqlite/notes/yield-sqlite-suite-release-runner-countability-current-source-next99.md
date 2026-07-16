# SQLite suite release-runner countability current-source next99

## Scope

This patch adds `SQLiteUpstreamSuiteEvidence::suiteReleaseRunnerCountabilityCurrentSourceNext99()`, a lane-local blocker-removal record for guarded SQLite release-runner artifacts on the launcher-printed accepted source.

The record admits one mapped countability unit only when all of these are true:

- launcher Base accepted HEAD is `796e75f2553d88aeff452968c875521a537dba2d`;
- dashboard, lane-status, and latest implementation source heads are explicit;
- the artifact path is lane-local under `lanes/libsqlite/`;
- the command is a guarded `testfixture ... testrunner.tcl --stop-on-error release ...` command;
- `testrunner.test` provenance is present;
- runner exit and errors are zero;
- no duplicate broad upstream runner is active;
- focused PHP output has the exact expected PASS-line delta.

It preserves already accepted release-runner anchors without mapping inflation and does not claim release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerCountabilityCurrentSourceNext99Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
PASS current source next99 blocks release parity active runners and pass inflation
PASS current source next99 rejects empty artifact rows

1 test files, 1617 assertions, 0 failures
```

PASS-line delta: `+107` focused PASS cases in `SQLiteSuiteReleaseRunnerCountabilityCurrentSourceNext99Test.php`.

## Countability Delta

- `phpPass`: `38278 -> 38385`
- mapped upstream evidence: `568 / 1589 -> 569 / 1589`
- release/all parity: unchanged and still gated on a separate broad zero-error artifact

## Non-Overlap

This avoids accepted release/all countability next75, current-source rebase next82, mixed artifact-directory split next93, upstream-runner admission burnup next94, accepted-head suite-denominator admission, and the batch68-batch94 ATTACH/JSON/pager/VFS/WAL/B-tree/pragma/planner behavior clusters.

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact rows, explicit accepted-source provenance, guarded release-runner command metadata, active-runner duplicate gates, and focused TestRunner PASS-line output only.
