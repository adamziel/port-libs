# Release Runner Upstream Map Current Next26

## Scope

This slice adds a lane-local current-to-next upstream runner map for SQLite
release/all evidence. It composes existing accepted-HEAD artifact provenance,
runner hydration, full-suite command manifest readiness, and duplicate broad
runner detection into one record before launching another guarded runner.

It intentionally does not launch a broad upstream suite and does not increase
`benchmarkDenominator.mapped`: no new upstream inventory unit was mapped. The
behavior removes a countability/admission ambiguity by making the current
accepted artifact and next-source runner gate explicit.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamMapTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 279 assertions, 0 failures
```

Syntax checks:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamMapTest.php
```

Both reported no syntax errors.

## Non-Overlap

This avoids the accepted current-next23 guarded preflight countability,
current-next24 hydration cluster, focused artifact admission, accepted-HEAD
provenance batch, release blocker closure, and ordinary libsqlite behavior
clusters such as JSON table cursors/source wiring, VFS file writer/sync/lock
state, WAL checkpoint/savepoint byte truncation, B-tree page relocation/root
collapse/overflow freelist release, Unicode GLOB, grouped SELECT SQL, and
SELECT subquery/expression ORDER BY execution.

## Dependency Closure

No new support component is needed. The record only reuses lane-local manifest
evidence, bounded runner artifact parsing, hydration probes, command manifest
planning, and duplicate-runner process snapshots.
