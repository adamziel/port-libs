# Real Upstream Corpus Trigger/FK Dynamic

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T232400Z-0`
Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`

Added `SQLiteRealUpstreamTriggerFkeyProgramExecutionMatrixTest.php`, a generic
real-upstream trigger-program execution matrix over the existing native
`SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution()` model.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-2.$ii-before`: BEFORE trigger programs execute as if their body
    statements ran before the triggering row change.
  - `trigger2-2.$ii-after`: AFTER trigger programs execute as if their body
    statements ran after the triggering row change.
  - Trigger program bodies covered: update from `OLD`, insert from `NEW`,
    delete log rows, compound insert/update/delete program, and insert-select
    from the mutating table.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyProgramExecutionMatrixTest.php`
  - `1 test files, 24007 assertions, 0 failures`
  - `22504` focused PASS lines.

Non-overlap:

- This avoids accepted fkey1/fkey2/fkey5/fkey6/fkey7/fkey8, trigger1
  lifecycle/update-delete, trigger2 row-order/selective/cascade/changes/conflict
  batches, triggerA, triggerC/D/E/F/G, trigger8, drop-trigger, PRAGMA FK, WAL,
  VFS, B-tree, JSON, SELECT, and source-neutral cleanup surfaces.
- This slice owns the real upstream `trigger2.test` trigger program execution
  section 2 matrix only.

Dependency closure:

- No new support component is needed. The slice reuses the existing generic
  native PHP trigger-program execution helper and upstream-cache source files.
