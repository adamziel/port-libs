# final-root-query-plan-scalar-output-current-base-20260602T0213Z

Slice: `libsqlite-final-root-query-plan-scalar-output-current-base-20260602T0213Z`
Base: `5915c5334b2df31bc80165ae091185eaf996e807`

## Behavior

- Reproduced the current-base broad failure in `SQLiteHeaderTest.php`: `1 test files, 9482 assertions, 1 failures`.
- Root cause: parser-level `json_tree()` JOIN rows can carry `SQLiteJsonSubtypeValue` objects for container `value` cells while later `ON`/`WHERE` predicates select scalar rows. `SQLiteSelectProjection` already normalizes those values for final output, but `SQLiteSelectResult` validated and keyed rows before that projection step.
- Fix: `SQLiteSelectResult` now normalizes `SQLiteJsonSubtypeValue` to its JSON text before scalar validation, DISTINCT keys, and ORDER comparison.

## Source Truth

- `sqlite3 :memory: "select 110/3, typeof(110/3), 24/3, typeof(24/3), 110/3.0, typeof(110/3.0);"` returned integer division for integer operands.
- `sqlite3` accepted unbound `?` and named parameters as SQL NULL values.
- `sqlite3` accepted `CROSS JOIN ... ON`, `SELECT load_policy FROM ... GROUP BY load_policy`, aggregate projections, aggregate `HAVING`, and aggregate expressions in `ORDER BY`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectResult.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` -> `1 test files, 9906 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 10 assertions, 0 failures`.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.

## Handoff

- Expected counter movement: `phpPass` `6290283 -> 6290284`, `phpFail` `1 -> 0`.
- Mapped coverage movement: none.
- Dependency closure: no new support component is needed; this reuses the existing JSON table, JSON subtype, SELECT SQL, SELECT query-plan, and SELECT result surfaces.
- Root harness: not run; isolated micro-slice.
- Non-overlap: avoids upstream corpus expansion, VFS/WAL/B-tree/storage work, JSON table planner pushdown, PDO invalid-DML work, and source-neutral cleanup beyond running the existing guard.
