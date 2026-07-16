# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T233430Z-0

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.

Behavior covered:

- Extended row-value `UPDATE` / `DELETE` limited-statement parity with `CAST(... AS INTEGER|INT)` in `LIMIT` and `OFFSET` expressions.
- The fixed cases combine outer UPDATE/DELETE limited statements, comma-form limits, row-value `IN (SELECT ...)` tuple sources, ordered tuple subqueries, and source-order RETURNING checks.
- The dynamic cases add 72 seed-driven UPDATE/DELETE windows over cast text-numeric limit and offset values.

Upstream parity source:

- SQLite `UPDATE ... ORDER BY ... LIMIT/OFFSET` behavior from upstream `e_update.test` sections `e_update-3.1` through `e_update-3.5`.
- SQLite `DELETE ... ORDER BY ... LIMIT/OFFSET` behavior from upstream `e_delete.test` sections `e_delete-3.2` through `e_delete-3.10`.
- SQLite row-value tuple predicate behavior from upstream `rowvalue.test`.

Focused growth:

- Added 86 focused TestRunner PASS cases to `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- Result: `1 test files, 646 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP UPDATE/DELETE RETURNING SQL parser, row-value tuple subquery evaluator, expression evaluator, and LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the existing row-value/update-delete-limit dynamic parity file with cast integer limit/offset expressions. It does not repeat prior arithmetic, quoted integral, unary plus, negative limit/offset, grouped SELECT, JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, or metadata-only suite evidence surfaces.
