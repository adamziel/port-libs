# real-upstream-corpus-trigger-fkey-dynamic-20260531T203421Z-0

Base accepted HEAD: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test`
- Ported section: `fkey1-6.0..6.2`

## Behavior

- A partial `UNIQUE` parent index (`CREATE UNIQUE INDEX p1x ON p1(x) WHERE y<2`) does not satisfy the required parent-key uniqueness for FK enforcement, even when the partial index contains the referenced parent row.
- The first child insert therefore reports `foreign key mismatch - "c1" referencing "p1"`.
- Adding a non-partial unique index on the same parent key repairs the parent lookup, and the same child insert commits.
- The planner keeps the mismatch state, full-index repair state, child row image, parent row match, and dependency evidence separate so future runner admission can distinguish the two upstream statements.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPartialIndexRepair20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T203421Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPartialIndexRepair20260531Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPartialIndexRepair20260531Test.php`
  - `1 test files, 4967 assertions, 0 failures`

## Countability

- Focused selected movement: `+4967` real TestRunner assertions/PASS lines.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth over an already mapped upstream script.

## Non-Overlap

This extends the existing `fkey1.test` partial-index coverage into the distinct repair step after `CREATE UNIQUE INDEX p1x2 ON p1(x)`. It does not repeat the accepted quoted-cascade, self-replace cascade, corrupt-stat, wide `foreign_key_check`, fkey2/fkey6/fkey8 action/counter, triggerupfrom, temptrigger, triggerC, triggerF, triggerG, WAL/VFS/B-tree/JSON/PRAGMA, or source-neutral cleanup batches.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local dynamic trigger/FK planner surface and the hydrated SQLite upstream checkout as source truth.
