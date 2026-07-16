# yield-sqlite-trigger-recursive-savepoint-upsert-current-next27

## Behavior

This slice adds `SQLiteRecursiveSavepointUpsertPlan`, a bounded native PHP model
for SQLite-style UPSERT execution inside a current savepoint when triggers
recursively enqueue more UPSERT rows.

Covered behavior:

- top-level INSERT and DO UPDATE conflict rows share one current savepoint image;
- BEFORE UPDATE triggers can rekey child metadata before FK checks;
- AFTER INSERT/UPDATE triggers can recursively UPSERT parent rows while
  `recursive_triggers` is enabled;
- yielded rows record event, depth, old/new keys, option name, and source
  trigger for depth-first statement evidence;
- immediate FK violations and trigger `RAISE(ROLLBACK)` restore the parent and
  child row arrays to the current savepoint image while preserving attempted row
  evidence and rollback diagnostics;
- recursive triggers can be disabled for top-level-only Application import
  preflight behavior.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveSavepointUpsertCurrentNext27Test.php
```

Result:

```text
1 test files, 65 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/application-recursive-savepoint-upsert.php
```

## Non-Overlap

This avoids the accepted `upsert-trigger-fk-yield-current-next23` standalone
UPSERT/FK yield helper and the accepted `trigger-recursive-savepoint-current-
next21` standalone recursive trigger savepoint helper. The new surface is their
uncovered intersection: recursive trigger-generated UPSERT rows under a current
savepoint, including RAISE rollback restoration and yielded row diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-array
execution, trigger metadata, and savepoint semantics; no ext/sqlite, shell-out,
or shared dependency activation is required.
