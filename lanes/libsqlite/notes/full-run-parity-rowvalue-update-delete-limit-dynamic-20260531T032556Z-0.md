# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T032556Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates constant text scalar LIMIT
  expressions for `upper()`, `lower()`, `trim()`, `ltrim()`, `rtrim()`,
  `substr()`, and `substring()` when the result is losslessly coercible to an
  integer.
- Focused tests cover both outer UPDATE/DELETE ORDER BY LIMIT windows and
  row-value `IN (SELECT ...)` tuple subqueries using those scalar text
  expressions for LIMIT/OFFSET.
- Malformed text-scalar LIMIT expressions still reject NULL, non-integral text,
  wrong arity, and non-integral substring indexes.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression coercion and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 6145 to
  6710 assertions.
- Added 165 focused TestRunner PASS cases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 6710 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the current row-value/update-delete-limit dynamic parity file
  with constant text scalar LIMIT/OFFSET functions only. It does not repeat the
  accepted cast, arithmetic, predicate, NULL ordering, Unicode length, concat,
  round/sign, DISTINCT/compound tuple-source, JSON, WAL/VFS, B-tree, PRAGMA,
  trigger/FK, or suite-evidence surfaces.
