# real-upstream-corpus-upsert-returning-dynamic-20260530T214900Z-0

Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Ported sections:
  - `upsert5-2.1`: unresolved expression conflict target reports `no such table: nosuchtable` before execution.
  - `upsert5-3.0` through `upsert5-3.2`: redundant `ON CONFLICT(bb)` arms after `REPLACE` preserve table and unique-index state.
  - `upsert5-3.3` through `upsert5-3.6`: redundant `ON CONFLICT(bb)` / `ON CONFLICT(cc)` arms after `REPLACE` preserve table, `t1bb`, and `t1cc` scan parity.

## Status Delta

Added `SQLiteUpsertReturningDynamicCorpusPlan::unresolvedConflictTargetCase()` and `redundantConflictIntegrityCases()` plus focused real-upstream PHP tests for the tail of `upsert5.test`.

Focused result:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictIntegrityTest.php
1 test files, 918 assertions, 0 failures
```

This handoff does not update accepted dashboard counters directly. Expected integrator movement is focused PASS-line growth for one new selected PHP test file after clean replay.

## Non-Overlap

This slice does not repeat the accepted `upsert4` alias matrix, accepted broad `upsert5` catch-all priority matrix, UPSERT/RETURNING scope coverage, or source-neutral cleanup work. It targets the remaining redundant-conflict corruption regression tail and bad conflict-target resolution from real upstream `upsert5.test`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP UPSERT/RETURNING corpus helpers and row-array conflict-arm model.
