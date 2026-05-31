# real-upstream-corpus-trigger-fkey-dynamic-20260531T073849Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T073849Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- Ported sections: `trigger1-1.1.1`, `trigger1-1.1.2`, `trigger1-1.1.3`, `trigger1-1.2.0`, `trigger1-1.2.1`, `trigger1-1.2.2`, `trigger1-1.2.3`, `trigger1-1.3`, `trigger1-1.4`, `trigger1-1.5`, `trigger1-1.6.1`, `trigger1-1.6.2`, `trigger1-1.7`, `trigger1-1.8`, `trigger1-1.9`, `trigger1-1.12`, `trigger1-1.13`, `trigger1-1.14`, `trigger1-2.1`, and `trigger1-2.2`.

Behavior added:

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::trigger1SchemaLifecycleCorpus()`.
- Models trigger create-time table resolution for main/temp schemas, unsupported `FOR EACH STATEMENT`, duplicate trigger handling, transaction rollback of created/dropped triggers, drop-table trigger cleanup, temp-trigger catalog isolation, system-table rejection, view/table timing rules, and parser-error rollback for malformed trigger bodies.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger1SchemaLifecycle20260531Test.php` with 130 generated upstream-backed variants and 4,292 focused TestRunner PASS cases.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1SchemaLifecycle20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1SchemaLifecycle20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1SchemaLifecycle20260531Test.php`
  - `1 test files, 4695 assertions, 0 failures`

Dashboard movement:

- Focused PHP PASS cases: `+4292`.
- Focused PHP assertions: `+4695`.
- `phpPass`: `2717884 -> 2722176`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this ports behavior for already mapped upstream trigger corpus sections.

Non-overlap:

- This does not repeat the accepted trigger1 late regression slice (`trigger1-17.0` through `trigger1-24.2`), triggerB view/name-resolution coverage, triggerC lifecycle/default/affinity coverage, trigger2 conflict propagation, fkey action matrices, fkey2/fkey5/fkey6/fkey7/fkey8 dynamic batches, schema reparse, JSON table, VFS/WAL, B-tree, PRAGMA, or source-neutral cleanup. The new surface is specifically early `trigger1.test` schema lifecycle and trigger DDL/parser rollback behavior.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local real upstream trigger/FK corpus planning class and the hydrated SQLite upstream checkout as source truth.

Follow-up:

- Continue trigger/FK dynamic corpus work with non-overlapping trigger DML execution sections or remaining FK commit/savepoint behavior that is not already covered by current fkey dynamic batches.
