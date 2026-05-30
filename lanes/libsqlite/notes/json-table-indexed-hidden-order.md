# JSON Table Indexed Hidden ORDER Current Source Next122

This slice adds `SQLiteJsonTablePlan::currentSourceIndexedHiddenOrder()`
on top of the accepted current-source and indexed-constraint planner layers.
It tracks `ORDER BY` terms over JSON table hidden columns (`json`, `root`) so a
copied `wp_options` import preview can keep the selected visible indexed
constraint while still detecting when the hidden source payload/root changes
the current cursor's ORDER dependency.

Focused behavior:

- Reuses accepted JSON table current-source planning and next119 indexed
  visible-constraint costing.
- Adds a hidden ORDER profile with source hidden keys, per-row hidden keys,
  sorter requirement, hidden sort penalty, effective cost class, transitions,
  and next122 replan reasons.
- Distinguishes hidden source/root ORDER changes from visible `id` streaming
  order and from unchanged stable current/next plans.
- Adds a Application smoke for copied plugin rule metadata in `wp_options`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedHiddenOrderTest.php`
  - `1 test files, 54 assertions, 0 failures`
  - 54 PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCostTest.php lanes/libsqlite/tests/SQLiteJsonTableIndexedHiddenOrderTest.php`
  - focused regression pair for accepted next119 plus next122
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableIndexedHiddenOrderTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-indexed-hidden-order.php`
- `php lanes/libsqlite/examples/application-json-table-indexed-hidden-order.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta:

- `phpPass`: `47656 -> 47710` (`+54` verified focused PASS lines)
- `phpFail`: unchanged at `0`
- Mapped upstream coverage: unchanged at `604 / 1589`; this is focused PHP
  behavior over already mapped JSON table planner/current-source inventory.

Dependency closure: no new support component is needed. This reuses native JSON
table planning, current-source hidden argument metadata, indexed visible
constraint costing, row-array filtering, and Application example bootstrap.

Non-overlap: this does not repeat accepted JSON visible/hidden constraint
pushdown, parser-level JSON table SELECT sources/cursor behavior, rowid hidden
constraints, lateral hidden constraints, or next119 indexed visible constraint
costing. The new behavior is the hidden `json`/`root` ORDER dependency layered
over an already selected indexed visible constraint.
