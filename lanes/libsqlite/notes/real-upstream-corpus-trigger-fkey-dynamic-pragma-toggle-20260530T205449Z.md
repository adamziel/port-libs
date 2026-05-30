# Real Upstream Trigger/FK Dynamic Pragma Toggle

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T205449Z-0`

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported section: `fkey2-8.1..8.16`, the `PRAGMA foreign_keys` transaction-boundary matrix.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeysPragmaToggleTransaction()`.
- Models autocommit `PRAGMA foreign_keys` toggles, read probes, ignored toggles inside `BEGIN`, ignored toggles inside nested savepoints, `RELEASE`, `COMMIT`, and `ROLLBACK` boundary state.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPragmaToggleCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicPragmaToggleCorpusTest.php` passed: `1 test files, 17508 assertions, 0 failures`.

Dashboard movement if accepted:

- Focused PHP PASS/assertion delta: `+17508`.
- `lane-status.json` `phpPass`: `684212 -> 701720`.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth, not mapped-denominator growth.

Non-overlap:

- Does not repeat accepted trigger/FK deferred restrict, fkey7 read-set/OR FAIL, fkey8 action-journal, trigger2, trigger4, trigger5 undo, dynamic view, nocase repair, or self-reference batches.
- This slice targets fkey2 transaction-time PRAGMA mutability specifically.

Dependency closure:

- No new support component is needed. The behavior is modeled in the existing native PHP dynamic trigger/FK plan helper and cites the hydrated upstream SQLite Tcl test file directly.
