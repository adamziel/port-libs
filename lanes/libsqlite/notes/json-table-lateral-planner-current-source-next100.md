# json-table-lateral-planner-current-source-next100

Behavior: adds keyed current-source planning for lateral `json_each()` /
`json_tree()` host rows. The planner now compares current and next JSON table
sources by a stable host key, so a reordered Application `wp_options` scan keeps
the correct current JSON source pinned while changed, added, or removed host
rows produce explicit next-source replan reasons.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralPlannerCurrentSourceNext100Test.php`
  - `1 test files, 63 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableLateralPlannerCurrentSourceNext100Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-lateral-planner-current-source-next100.php`
- `php lanes/libsqlite/examples/application-json-table-lateral-planner-current-source-next100.php --self-test`
  - `application-json-table-lateral-planner-current-source-next100 self-test passed`

Status delta:

- `phpPass`: `38278 -> 38341` (`+63` focused assertions).
- `benchmarkDenominator.mapped`: unchanged; this is focused current-source
  behavior over already mapped JSON table planner/source inventory.

Non-overlap: avoids accepted parser-level JSON table SELECT source wiring,
JSON table cursor iteration, hidden/visible constraint extraction, hidden rowid
source planning, rowid alias lateral joins, JSON grouped rows, and malformed
JSONB planner work. This slice is only the keyed lateral current-source
transition where host rows can reorder across current/next scans.

Dependency closure: no new support component is needed. The slice reuses the
lane-local JSON table planner, current-source planner, JSON1/JSONB readers,
and Application row-array smoke harness.
