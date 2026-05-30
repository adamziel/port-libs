# real-upstream-corpus-trigger-fkey-dynamic-20260530T212305Z-0

Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
- Ported scenario ranges:
  - `fkey8-2.1.2..2.3.1`: deferred foreign-key counters include implicit deletes from `INSERT OR REPLACE`, including WITHOUT ROWID parent/child replacement and trigger-side replacement.
  - `fkey8-7.0..7.4`: attached-schema `ON UPDATE CASCADE` updates child rows in the same attached schema without routing through a same-named main schema table.

Implemented behavior:

- Added generic `SQLiteDynamicTriggerForeignKeyPlan::replaceDeferredForeignKeyCounter()`.
- Added generic `SQLiteDynamicTriggerForeignKeyPlan::attachedSchemaCascadeUpdate()`.
- Extended `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php` with 401 distinct upstream-derived TestRunner cases and 5,026 new focused behavior assertions.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - Result: `1 test files, 22736 assertions, 0 failures`
  - Previous focused file assertion count before this slice: `17710`
  - New focused assertion delta: `+5026`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`

Non-overlap:

- This slice extends the accepted trigger/FK dynamic corpus with `fkey8.test` implicit-delete deferred-counter and attached-schema cascade behavior.
- It does not repeat accepted `fkey1`, `fkey2`, `fkey6`, `fkey7`, trigger1 lifecycle/update-delete, trigger2 view, trigger4 view, trigger5 undo, trigger7, triggerG recursive trigger, UPSERT/RETURNING, pager/WAL, B-tree, JSON, SELECT, or source-neutral cleanup surfaces.
- Mapped denominator coverage remains unchanged because `fkey8.test` is already in the hydrated upstream inventory; this is behavior/PASS growth, not a new denominator row.

Dependency closure:

- No new support component is needed. The slice reuses lane-local PHP row-array trigger/FK modeling and reads the hydrated upstream Tcl source only as source-truth evidence. No SQLite extension, external service, or live upstream runner is required for the focused PHP tests.

Root harness: not run - isolated micro-slice.
