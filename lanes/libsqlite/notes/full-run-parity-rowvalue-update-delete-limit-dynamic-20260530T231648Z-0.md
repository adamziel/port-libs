# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T231648Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE RETURNING LIMIT parity.

Implemented support in `SQLiteUpdateDeleteReturningSql` for unary negative LIMIT/OFFSET expressions around parenthesized arithmetic, such as `LIMIT -(1+1)` and `OFFSET -(2)`. This keeps SQLite's negative LIMIT semantics as "no limit" and negative OFFSET semantics as clamped to zero through the existing `SQLiteUpdateDeleteLimitPlan` selection path.

Also added `length(...)` scalar evaluation for UPDATE/DELETE ORDER BY and row-value subquery ORDER BY expressions. The focused test covers computed `ORDER BY length(key_name)` row selection, source-order RETURNING output, and row-value subquery tuple selection before UPDATE matching.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` negative LIMIT/OFFSET behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` UPDATE/DELETE LIMIT expression and `ORDER BY length(...)` evidence.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` UPDATE LIMIT/OFFSET and ORDER BY selection semantics.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` -> `1 test files, 344 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses the existing bounded PHP UPDATE/DELETE RETURNING executor and row-value tuple source helper.

Non-overlap: this slice does not repeat accepted row-value negative-offset, unary-plus, quoted LIMIT, update/delete returning, JSON, WAL, VFS, B-tree, trigger, PRAGMA, or suite-evidence clusters. The new behavior is specifically unary negative parenthesized LIMIT/OFFSET evaluation plus computed `length(...)` ordering in UPDATE/DELETE selection and row-value subqueries.
