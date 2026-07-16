# SQLite UPSERT Trigger/FK Yield Current Next23

## Behavior

Adds `SQLiteUpsertTriggerForeignKeyYieldPlan` for a bounded upstream-backed DML
cluster where `INSERT ... ON CONFLICT DO UPDATE` processes rows one at a time,
fires BEFORE/AFTER trigger effects, yields per-row status, and records
immediate/deferred foreign-key state over copied `wp_options` /
`wp_optionmeta`-style rows.

Covered behavior:

- conflict detection by UPSERT target with SQLite NULL non-conflict behavior;
- skipped `DO UPDATE WHERE` rows yielding without trigger or FK side effects;
- BEFORE UPDATE triggers rekeying child rows before the parent row changes;
- AFTER INSERT/UPDATE triggers inserting or repairing child rows;
- immediate FK rejection versus deferred transient/final violation evidence;
- Application smoke for option import diagnostics without `ext/sqlite`.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertTriggerForeignKeyYieldCurrentNext23Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Focused PASS-line delta: `+62` new `TestRunner` PASS cases.

Lane status delta: `phpPass` `8166 -> 8228`, `phpFail` remains `0`.

Mapped upstream denominator delta: `458 -> 459` for one focused DML/FK evidence
row. This does not claim a fresh upstream Tcl run.

## Non-Overlap

This slice avoids accepted batch21 ATTACH temp trigger FK resolution,
recursive trigger savepoint planning, UPSERT RETURNING trigger effects,
VFS/WAL/B-tree/page-move clusters, and recent SELECT SQL text/expression
ORDER BY work. It is a row-by-row UPSERT yield/FK timing cluster, not another
schema-resolution, RETURNING, savepoint, or storage-diagnostic helper.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local row
array planning and native PHP test infrastructure.
