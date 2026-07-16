# real-upstream-corpus-trigger-fkey-dynamic-20260530T230515Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T230515Z-0`

Status: added a real upstream trigger/FK dynamic behavior batch over generic
application settings rows.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test`
  - `triggerB-3.1..3.2`: wide `OLD`/`NEW` trigger column masks still track
    columns above the legacy bitmask width.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test`
  - `triggerF-1.*`: `WITHOUT ROWID` conflict deletes from `OR REPLACE` fire
    DELETE triggers with BEFORE/AFTER row-count visibility.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::wideColumnTriggerMaskPlan()`.
- Added `SQLiteDynamicTriggerForeignKeyPlan::withoutRowidConflictDeleteTriggerPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicWideMaskConflictTest.php` with
  12,377 focused TestRunner PASS cases/assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWideMaskConflictTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWideMaskConflictTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWideMaskConflictTest.php`
  - `1 test files, 12377 assertions, 0 failures`

Non-overlap:

- This does not repeat accepted trigger/FK batches for fkey1/fkey2/fkey6/fkey7,
  fkey8 action journals, trigger2 row timing, trigger3 RAISE rollback,
  trigger4 views, trigger5 undo, trigger7/trigger8/triggerG recursion,
  triggerA view WHERE behavior, triggerD rowid aliasing, or triggerE variable
  boundary behavior. The new upstream surfaces are specifically `triggerB`
  wide OLD/NEW column mask behavior and `triggerF` WITHOUT ROWID conflict
  delete trigger visibility.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  dynamic trigger/FK helpers and the hydrated upstream SQLite checkout as
  source truth.

Next:

- Continue trigger/FK corpus work only on non-overlapping upstream ranges, such
  as remaining `triggerC` recursive trigger subprogram variants or unclaimed
  `e_fkey` documented runtime-enforcement statements.
