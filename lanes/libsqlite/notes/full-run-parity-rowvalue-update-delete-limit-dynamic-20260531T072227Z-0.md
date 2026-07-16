# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T072227Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now evaluates SQL `AND` and `OR` truth
  composition inside constant `LIMIT` and `OFFSET` expressions.
- The evaluator preserves SQLite three-valued truth rules: `TRUE OR NULL`
  yields true, `FALSE AND NULL` yields false, and expressions that remain NULL
  are rejected as datatype mismatches when used as LIMIT/OFFSET integers.
- Focused tests exercise outer UPDATE windows, row-value `IN (SELECT ...)`
  DELETE subquery windows, source-order RETURNING checks, and malformed
  NULL/missing-operand logical LIMIT expressions over generic `app_settings`
  fixtures.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET expression and datatype-mismatch behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT row selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  ordered row-value tuple subquery LIMIT/OFFSET behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grows from 12548 to
  13036 focused assertions.
- Added 100 focused TestRunner PASS cases: 48 UPDATE logical LIMIT/OFFSET
  windows, 48 DELETE row-value logical subquery windows, and 4 malformed
  logical LIMIT/OFFSET guards.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 13036 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator,
  LIMIT/OFFSET selection plan, and SQL truth-value helper.

Non-overlap:

- This extends row-value/update-delete-limit dynamic parity with logical
  `AND`/`OR` LIMIT expression evaluation only. It does not repeat prior
  negative offset, arithmetic, casts, scalar functions, math functions,
  row-value BETWEEN/subquery comparison, grouped SELECT, JSON table, WAL/VFS,
  B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or metadata-only suite
  evidence surfaces.
