# Trigger/FK RETURNING Current-Next33

## Behavior

Adds `SQLiteTriggerForeignKeyReturningPlan` for bounded native PHP planning of
parent-row `UPDATE` and `DELETE` statements where trigger effects, foreign-key
actions, and `RETURNING` yield order interact.

Covered behavior:

- `UPDATE` parent keys with `ON UPDATE CASCADE` child-key rewrites.
- `DELETE` parents with `ON DELETE CASCADE` and `ON DELETE SET NULL`.
- `RETURNING` rows captured from the current parent image before after-trigger
  side effects.
- BEFORE/AFTER trigger audit rows and trigger-driven child inserts/updates.
- Deferred `NO ACTION` violation evidence with statement and after-trigger
  phases.
- Immediate `NO ACTION` rejection and malformed trigger/projection guards.

## Verification

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyReturningCurrentNext33Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 67 assertions, 0 failures
```

The lane `phpPass` status moves from `11206` to `11273` by the exact focused
PASS-line delta. `benchmarkDenominator.mapped` is unchanged because this slice
adds focused behavior coverage without adding a new upstream inventory unit.

## Non-Overlap

This slice does not repeat accepted UPSERT trigger/FK yield behavior,
UPSERT-trigger `RETURNING`, VFS writer/lock/sync, WAL checkpoint/savepoint byte
truncation, JSON table source/cursor/constraint pushdown, SQL expression
`ORDER BY`, expression-index range cost, Unicode GLOB, B-tree page
move/root-collapse/overflow-freelist, or rollback-journal commit/apply work.

## Dependency Closure

No new support component is needed. The plan reuses lane-local PHP row arrays
and trigger/FK planning primitives; parser-level `UPDATE`/`DELETE RETURNING`
wiring can use this as a future execution component.
