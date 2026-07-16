# Trigger Recursive Depth Current/Next25

## Scope

This slice adds bounded recursive-trigger current-depth/next-depth admission for copied `wp_options` import rows. It models the SQLite trigger-depth limit before entering the next trigger program, so over-limit child rows are not materialized.

## Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveDepthCurrentNext25Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 69 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +69, from 8739 to 8808, with `phpFail` unchanged at 0. The mapped upstream denominator is unchanged because this is focused PHP behavior coverage, not a fresh upstream inventory unit.

## Non-Overlap

This avoids accepted batch23 UPSERT trigger/FK yield coverage, prior DML trigger recursion/savepoint conflict corpus coverage, and all accepted WAL/VFS/B-tree/JSON/SELECT clusters. The new behavior is specifically current-depth/next-depth limit admission with abort, rollback, ignore, `recursive_triggers` suppression, and conflict interaction diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local trigger row templates and native PHP array execution; it does not require ext/sqlite, upstream binaries, or provider credentials.
