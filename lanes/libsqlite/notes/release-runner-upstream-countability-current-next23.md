# Release Runner Upstream Countability Current Next23

Slice: `yield-sqlite-release-runner-upstream-countability-current-next23`

## Behavior

Added `SQLiteUpstreamSuiteEvidence::guardedRunnerPreflightRecord()` to make a
guarded upstream-suite launch refusal countable without confusing it with a
SQLite test failure or a skipped release/all pass.

The current attempted guarded command was:

```sh
/home/claude/port-libs/scripts/run-sqlite-tcl-bounded-runner.sh libsqlite-current-next23-countability lanes/libsqlite/notes/sqlite-current-next23-countability.md lanes/libsqlite/notes/sqlite-current-next23-countability.tmp lanes/libsqlite/notes/sqlite-current-next23-countability.log veryquick 1 300 json101.test json102.test jsonb01.test
```

It did not produce a Tcl artifact because the runner stopped before launch:

```text
[2026-05-27T19:51:43Z] libsqlite-current-next23-countability start
[2026-05-27T19:51:43Z] stop: root free 39411300 KiB < 80 GiB
```

The new record preserves the runner label, supervisor-approval state, duplicate
runner gate, command-manifest readiness, disk requirement, root free KiB,
required KiB, and shortfall. It explicitly sets `counts_as_release_parity` to
false and points the next gate at freeing enough root disk before rerunning the
same accepted-HEAD command.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamCountabilityCurrentNext23Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS current next23 records guarded runner disk gate as non-countable upstream evidence
PASS current next23 keeps supervisor and duplicate runner gates ahead of disk countability
PASS current next23 reports launch ready when guarded preflight output has no stop blocker
PASS current next23 rejects invalid guarded runner job counts

1 test files, 62 assertions, 0 failures
```

The 62 PASS-line delta updates `lane-status.json` `phpPass` from `8166` to
`8228`. `benchmarkDenominator.mapped` is unchanged; this is a release-runner
countability blocker classifier, not a newly mapped upstream inventory unit.

## Non-Overlap

This avoids accepted release countability current-next21, focused runner
artifact admission, accepted-HEAD artifact provenance batches, release blocker
closure records, pgrep self-probe filtering, permutation-suite command mapping,
wildcard expansion, and all accepted behavior clusters. It does not launch a
broad runner and does not count a preflight refusal as SQLite release parity.

## Dependency Closure

No new support component is needed. The preflight record composes existing
lane-local runner launch gates, command-manifest readiness, active-runner
snapshots, and guarded runner stdout only.
