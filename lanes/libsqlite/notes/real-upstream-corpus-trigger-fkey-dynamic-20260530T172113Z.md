# Real Upstream Corpus Trigger/FK Dynamic

Micro-slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T172113Z-0`

Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php` with 647 focused assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-4.*` recursive FK action intent.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
  - deferred FK transaction/commit behavior around `PRAGMA defer_foreign_keys`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
  - recursive trigger execution and max-depth guard behavior.

Focused behavior ported:

- 130 generated chain lengths exercise distinct recursive trigger/FK depths.
- Each chain checks recursive update count, deferred violation count, trigger-effect count, and tail-row materialization.
- Nonrecursive mode checks statement-row-only updates and valid remaining child references.
- Rollback mode checks savepoint restoration of parent rows, page images, and WAL frame discard.
- Max-depth guard checks the trigger recursion failure path.

Non-overlap:

This does not add metadata-only denominator rows and does not repeat accepted trigger/FK next corpus rows. It stresses dynamic recursive-chain depth, deferred violation commit boundaries, nonrecursive suppression, and rollback/WAL cleanup using generic setting/key rows.

Dependency closure:

No new support component is needed. The slice reuses the existing native trigger/FK current-source planner and the hydrated upstream SQLite test checkout as source truth.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php`
  - `1 test files, 647 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `208305 -> 208952` (`+647`)
- mapped coverage remains `958 / 1589`
