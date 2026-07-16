# Focused Subset Run Record Next11

## Scope

- Added focused release-suite blocker coverage for `SQLiteUpstreamSuiteEvidence::focusedSubsetRunRecord()`.
- Covers hydrated selected-script command admission, skipped non-hydrated worktrees, parsed zero-error and failing Tcl summaries, path-based `test/`, `ext/fts5/test/`, and `mptest/` selections, wildcard command preservation, and invalid absolute/shell/space script guards.
- Tightened focused subset script admission to reject absolute `.test` paths so generated commands stay relative to the hydrated SQLite checkout/build tree.

## Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteFocusedSubsetRunRecordNext11Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 225 assertions, 0 failures
```

- PASS-line delta: `+50`.
- `lane-status.json` `phpPass`: `3796 -> 3846`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP blocker coverage, not a newly mapped upstream inventory unit.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This avoids accepted focused artifact admission, artifact-set provenance, closure-record, pgrep self-probe, and broad release ledger clusters. It focuses only on selected-script subset run records and command-admission safety before a runner is launched.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local manifest, fake hydrated cache fixtures, and existing `SQLiteUpstreamSuiteEvidence` parsing.
