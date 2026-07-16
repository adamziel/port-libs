# real-upstream-corpus-trigger-fkey-dynamic-20260531T035915Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T035915Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test`
- Ported sections: `triggerB-1.1`, `triggerB-1.2`, `triggerB-2.1`, `triggerB-2.2`, `triggerB-2.3`, and `triggerB-2.4`

Behavior added:

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::triggerBViewUpdateAndNameResolution()`.
- Models temp view `INSTEAD OF UPDATE OF y` trigger routing so only the view update trigger writes through to the base rows, preserving the unmentioned view column.
- Models trigger body name-resolution runtime errors for unknown qualified names and invalid `OLD` column references.
- Models rowid primary-key updates being visible to an `AFTER UPDATE` trigger body.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerBViewNameTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerBViewNameTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerBViewNameTest.php`
  - `1 test files, 2591 assertions, 0 failures`

Dashboard movement:

- Focused PHP assertions: `+2591`.
- `phpPass`: `1960508 -> 1963099`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this ports behavior for already mapped upstream trigger corpus sections.

Non-overlap:

- This does not repeat accepted triggerB wide OLD/NEW bitmask coverage, triggerC rowid/constant-loop behavior, trigger9 view rowid behavior, triggerD alias behavior, triggerE variable handling, triggerF WITHOUT ROWID conflict behavior, fkey action matrices, fkey2 count_changes/self-reference/REPLACE behavior, temp trigger routing, schema reparse, JSON table, VFS/WAL, B-tree, PRAGMA, or source-neutral cleanup. The new surface is specifically `triggerB-1.*` temp view `INSTEAD OF UPDATE OF` routing and `triggerB-2.*` trigger-body name resolution plus rowid-update visibility.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local real upstream trigger/FK corpus planning class and hydrated SQLite upstream checkout as source truth.

Follow-up:

- Continue trigger/FK dynamic corpus work with a non-overlapping upstream range, preferably remaining `triggerB` name-resolution or trigger body execution edge cases not already covered by wide column-mask tests.
