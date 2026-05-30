# DML Trigger Current/Next Row Images

Slice: `yield-sqlite-insert-update-delete-trigger-current-next19`

This patch adds `SQLiteDmlTriggerCurrentNextPlan`, a bounded native PHP row-array
executor for SQLite-style trigger row images across `INSERT`, `UPDATE`, and
`DELETE`. It records `OLD` and `NEW` values for Application `wp_options` style
mutations, including generated rowids for inserts, `UPDATE OF` column filtering,
`WHEN` predicates, ordered/limited update and delete selection, and strict
guards for invalid `OLD`/`NEW` references.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDmlTriggerCurrentNextTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `PASS captures insert update delete old and new trigger row images`
  - `PASS guards malformed dml trigger current next definitions`
  - `1 test files, 53 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteDmlTriggerCurrentNextPlan.php && php -l lanes/libsqlite/tests/SQLiteDmlTriggerCurrentNextTest.php && php -l lanes/libsqlite/examples/application-dml-trigger-current-next.php`
  - no syntax errors detected in all changed PHP files
- `php lanes/libsqlite/examples/application-dml-trigger-current-next.php`
  - passed; output reports remaining `siteurl`/`blogname` rows and insert/update/delete audit events
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- Avoids accepted parser-level SELECT SQL text, JSON table cursor/source/hidden
  constraints, VFS writer/sync/lock/rollback paths, B-tree page move/root
  collapse/overflow release, Unicode GLOB, and prior insert-recursion or
  update/delete order-only trigger helpers.

Dependency closure:

- No new support component is required. The slice reuses native row-array DML
  and trigger metadata already present in the libsqlite lane.
