# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T043754Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior:

- `SQLiteUpdateDeleteReturningSql` now accepts SQLite planner-hint scalar
  functions `likely()`, `unlikely()`, and `likelihood()` in UPDATE/DELETE
  LIMIT and OFFSET expressions. The hint value is evaluated as SQLite's
  pass-through expression value; `likelihood()` validates its probability
  argument as numeric.
- Focused tests cover outer UPDATE windows, row-value DELETE subquery windows,
  and malformed arity/probability/NULL/non-integral cases.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for LIMIT
  integer-expression parity.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test` for
  row-value tuple source behavior.

Focused evidence:

- Before this slice, `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  passed with 8201 assertions.
- After this slice, it passes with 8543 assertions.
- Focused assertion growth: +342 assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`

Dependency closure:

- No new support component is needed. This reuses the existing generic
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This extends the accepted row-value/update-delete-limit dynamic parity file
  only for `likely`/`unlikely`/`likelihood` LIMIT scalar hints. It avoids the
  prior negative offset, cast, boolean, CASE, scalar SELECT, predicate, Unicode
  length, concat, round, sign, substring, instr, replace, char, unicode, iif,
  rowvalue3, and rowvalue4 clusters, and it adds no domain-specific source
  text.
