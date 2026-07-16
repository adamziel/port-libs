# real-upstream-corpus-trigger-fkey-dynamic-20260531T042715Z-0

Status: ready for integration.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported section: `triggerC-4.1.1..4.1.9`

Behavior added:

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCAffinityTiming()` as a bounded native PHP model for triggerC affinity timing.
- The model records that INSERT/UPDATE non-rowid values are coerced by column affinity before `BEFORE` triggers see `NEW`.
- It preserves the upstream `NEW.rowid = -1` behavior for automatically assigned rowids in `BEFORE INSERT` triggers, with the stored rowid visible to `AFTER` and DELETE triggers.
- It records OLD/NEW UPDATE trigger images and keeps REAL-affinity `typeof()` visible as `real` even for exact integer values.

Changed files:

- `lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCAffinityTimingTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T042715Z-0.md`
- `lanes/libsqlite/lane-status.json`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCAffinityTimingTest.php`
  - Result: `1 test files, 4424 assertions, 0 failures`
  - PASS lines: `3604`

Expected dashboard movement:

- `phpPass`: `2070862 -> 2074466` from the new focused PHP TestRunner PASS lines.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior growth against already mapped real upstream trigger corpus.

Dependency closure:

- No new support component is needed. This reuses the hydrated upstream SQLite test checkout and the existing lane-local trigger/FK dynamic plan surface.

Non-overlap:

- This does not repeat accepted triggerC recursion, OR REPLACE delete-trigger firing, rowid mutation, constant-loop, quoted target, before-update self-mutation, triggerB/D/E/G behavior, fkey action/defer/check families, JSON/VFS/WAL/B-tree/PRAGMA/SELECT clusters, or source-neutral cleanup. The new surface is specifically `triggerC-4.1` affinity and rowid image timing in trigger programs.

Next:

- Continue trigger/FK corpus with another non-overlapping upstream section, preferably remaining triggerC conflict/depth or fkey edge behavior that is not already covered by the dynamic corpus files.
