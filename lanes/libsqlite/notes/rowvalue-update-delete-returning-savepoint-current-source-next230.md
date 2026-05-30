# Row-Value UPDATE/DELETE RETURNING Nested Savepoint Current-Source Next230

## Scope

- Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext230Plan`, an additive current-source plan for nested savepoint behavior around row-value `UPDATE` and `DELETE ... RETURNING`.
- Models an inner savepoint that is released before an outer `ROLLBACK TO`; the inner and after-release RETURNING rows are recorded as discarded, and retry statements read the restored outer savepoint image.
- Covers Application copied `wp_options` / `wp_optionmeta` migration cleanup paths using row-value `IN (SELECT ...)`, `SELECT DISTINCT`, `ORDER BY`, and `LIMIT` subqueries.

## Evidence

- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext230Test.php`
- Result:
  `1 test files, 67 assertions, 0 failures`
- PASS-line delta:
  `+67` focused PASS cases for this isolated next230 slice.
- Example smoke:
  `php lanes/libsqlite/examples/application-rowvalue-nested-savepoint-current-source-next230.php`

## Non-Overlap

- Avoids accepted/simple row-value rollback next212, OR FAIL next207, OR ABORT next200, OR ROLLBACK/RELEASE variants, trigger RETURNING, JSON table, planner, WAL/VFS, and B-tree clusters.
- This slice is about nested savepoint current-source images and RETURNING stream suppression after inner `RELEASE` plus outer `ROLLBACK TO`.

## Dependency Closure

- No new support component is needed.
- Reuses native row-value `UPDATE`/`DELETE RETURNING`, subquery row-value predicates, and savepoint current-source image planning already present in `lanes/libsqlite`.

## Next

- A follow-up can wire the same nested-savepoint suppression into a broader transaction executor once the lane owns a parser-level DML transaction dispatcher.
