# real-upstream-corpus-trigger-fkey-dynamic fkey5 foreign-key-check

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`
- Sections: `fkey5-1.2`, `fkey5-2.0`, `fkey5-3.0`, `fkey5-4.0`, `fkey5-4.2`, `fkey5-4.4`, `fkey5-5.0`, `fkey5-5.2`, `fkey5-5.4`, `fkey5-6.0`, `fkey5-6.2`, `fkey5-6.4`, `fkey5-7.1`, `fkey5-8.0`, `fkey5-8.2`, `fkey5-8.6`, `fkey5-9.1.3`, `fkey5-9.4`, `fkey5-10.3`, `fkey5-11.1`, `fkey5-12.0`, `fkey5-12.1`, `fkey5-13.11`, and `fkey5-13.12`.
- Behavior: `PRAGMA foreign_key_check` result shape, table/schema/table-valued arguments, missing parent tables, collation/affinity-sensitive parent matching, WITHOUT ROWID child `NULL` rowid reporting, mismatch diagnostics, and virtual-table ordering.

## Local movement

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::fkey5ForeignKeyCheckMatrix()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicFkey5ForeignKeyCheck20260531Test.php`.
- Focused result: `1 test files, 6615 assertions, 0 failures`.
- Focused PASS lines: `6543`.
- Expected selected movement: `+6543` focused PASS lines after clean integration.
- Mapped denominator movement: none; mapped coverage is already `1589 / 1589`.

## Non-overlap

This does not repeat the existing triggerG, trigger2 row timing/conflict propagation, triggerB view update/name-resolution, triggerC recursive/default/affinity timing, fkey2 deferred transaction, fkey7 dependency-read/zeroblob/OR FAIL, fkey8 statement-journal, e_fkey38 deferred savepoint, or prior e_fkey action-matrix coverage. This slice owns the upstream `fkey5.test` foreign-key-check pragma matrix.

## Dependency closure

No new support component is needed. The slice reuses the existing generic upstream trigger/FK dynamic plan surface and the hydrated SQLite upstream checkout as source truth.
