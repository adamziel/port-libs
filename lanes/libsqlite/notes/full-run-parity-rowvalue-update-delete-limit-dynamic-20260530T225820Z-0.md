# full-run-parity-rowvalue-update-delete-limit-dynamic-20260530T225820Z-0

Base accepted HEAD: `6e94a67dd020b9cfec1567bd7fbc6ebe5e036bda`.

Behavior covered:

- Row-value `UPDATE` / `DELETE` predicates using `IN (SELECT ...)` now support expression `ORDER BY` terms in the tuple-producing subquery.
- The focused cases combine computed ordering (`bytes + blog_id DESC`) with dynamic arithmetic `LIMIT` / `OFFSET` expressions, then verify selected rowids, returned row order, and preserved rows after mutation.

Upstream parity source:

- SQLite row-value predicate behavior from upstream `rowvalue.test`.
- SQLite `UPDATE` / `DELETE ... ORDER BY ... LIMIT` selection behavior from upstream `update.test` / `delete.test` limit-order coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueCurrentSourceNext110Test.php` -> `1 test files, 59 assertions, 0 failures`.

Dependency closure:

- No new support component needed. This reuses the existing native PHP UPDATE/DELETE RETURNING SQL parser, row-value tuple subquery evaluator, expression evaluator, and LIMIT/OFFSET evaluator.
