# JSON table rowid hidden constraint current-source next99

This slice preserves original JSON table rowid alias provenance while keeping
SQLite-style hidden constraint execution normalized to `id`.

Behavior:
- `SQLiteJsonTablePlan::currentSourceRowidHiddenConstraintPlannerNext99()`
  reports `rowid`, `_rowid_`, and `oid` as original aliases, normalized to
  hidden `id`, across current-source to next-source JSON table planning.
- Parser-level `JOIN json_each/json_tree ... ON j.rowid/_rowid_/oid = ...`
  plan metadata now records both `originalColumn` and normalized `column`,
  while dynamic row filtering still uses the accepted hidden `id` constraint.
- The Application smoke uses copied `wp_options` JSON settings and shows
  `j.oid = o.target_rowid` selecting the expected `json_tree()` row without
  requiring `ext/sqlite`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenConstraintCurrentSourceNext99Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-rowid-hidden-constraint-current-source-next99.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenConstraintCurrentSourceNext99Test.php`
  - `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-rowid-hidden-constraint-current-source-next99.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta:
- `phpPass`: `38278 -> 38329` (`+51` focused PASS lines)
- mapped coverage: `568 / 1589 -> 569 / 1589`

Dependency closure:
- Reuses existing native PHP JSON table planner, parser-level SELECT/FROM
  execution, and JSONB/text input handling. No new support component is
  needed.

Non-overlap:
- Avoids accepted JSON table cursor/source execution, hidden `json`/`root`
  extraction, visible constraint pushdown, lateral rowid execution, batch94
  hidden rowid source row materialization, and Application JSON row projection.
  This patch is metadata/provenance behavior for rowid alias constraints while
  preserving accepted execution semantics.
