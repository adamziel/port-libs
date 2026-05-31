# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T074500Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Extends `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` with LIMIT and
  OFFSET scalar predicate expressions that reduce to integer windows.
- The added cases exercise `BETWEEN`, `NOT BETWEEN`, `IN`, `NOT IN`, `IS NOT
  NULL`, and tuple subquery selection through UPDATE and DELETE RETURNING paths.
- No production API or source-level domain names changed.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  expression-valued LIMIT/OFFSET parity.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple predicate behavior.

Focused growth:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grew from 13,036 to
  13,564 focused assertions.
- The slice adds 96 focused TestRunner PASS cases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 13564 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluator and
  row-value tuple source evaluator.

Non-overlap:

- This avoids the previously accepted row-value LIMIT cast, arithmetic,
  scalar-function, logical AND/OR, null-placement, min/max, trim, math, and
  scalar SELECT windows. It only adds predicate-valued LIMIT/OFFSET expressions
  over the existing generic `app_settings` fixtures.
