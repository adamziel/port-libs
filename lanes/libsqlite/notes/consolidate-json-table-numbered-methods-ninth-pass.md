# JSON table numbered methods ninth pass

Consolidated the JSON-table lateral current-source planner wrappers into stable
canonical method/helper names on `SQLiteJsonTablePlan`:

- `lateralCurrentSourcePlanner`
- `hiddenConstraintSourceCurrentSource`
- `lateralHiddenConstraintCurrentSource`
- `lateralRowidHiddenCurrentSource`

Also renamed the direct private helpers for keyed host-row handling and migrated
the direct JSON-table tests/examples to the canonical entrypoints. No numbered
production class/file/helper was added, and no compatibility shim remains for
the removed method names.

Verification:

- `php -l` on `SQLiteJsonTablePlan.php`, the four changed JSON-table tests, and
  the four changed JSON-table examples: all passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralPlannerCurrentSourceNext100Test.php lanes/libsqlite/tests/SQLiteJsonTableHiddenConstraintSourceCurrentSourceNext102Test.php lanes/libsqlite/tests/SQLiteJsonTableLateralHiddenConstraintCurrentSourceNext103Test.php lanes/libsqlite/tests/SQLiteJsonTableLateralRowidHiddenCurrentSourceNext105Test.php`
  passed: 4 test files, 242 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-json-table-lateral-planner-current-source-next100.php --self-test` passed.
- `php lanes/libsqlite/examples/wordpress-json-table-hidden-constraint-source-current-source-next102.php --self-test` passed.
- `php lanes/libsqlite/examples/wordpress-json-table-lateral-hidden-constraint-current-source-next103.php --self-test` exited 0 with the expected JSON smoke payload.
- `php lanes/libsqlite/examples/wordpress-json-table-lateral-rowid-hidden-current-source-next105.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: reuses the existing native JSON table planner, hidden
constraint planner, keyed host-row source tracking, JSONB decoder, and test
runner. No new support component is needed.
