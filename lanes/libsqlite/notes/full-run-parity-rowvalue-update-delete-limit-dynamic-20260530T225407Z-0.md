Status: focused PHP behavior growth for row-value UPDATE/DELETE LIMIT parity.

This slice extends the existing dynamic row-value UPDATE/DELETE RETURNING
LIMIT parity coverage with two SQLite upstream-backed edges:

- negative OFFSET inside row-value `IN (SELECT ... LIMIT ... OFFSET ...)`
  tuple sources is clamped to zero before tuple matching, matching
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  `limit-1.2.5`;
- unary plus LIMIT/OFFSET integer expressions are accepted for both
  `LIMIT +n OFFSET +m` and comma-form `LIMIT +offset, +count`.

Changed behavior:

- `SQLiteUpdateDeleteReturningSql::limitExpressionValue()` now handles unary
  plus by evaluating the wrapped expression.
- `SQLiteUpdateDeleteReturningSql::rowValueSelectTupleList()` clamps negative
  tuple-source offsets before `array_slice()`, avoiding PHP's from-tail
  negative-offset semantics.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
# 1 test files, 336 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses the
existing row-value UPDATE/DELETE RETURNING parser/executor and LIMIT expression
evaluator.

Non-overlap: extends the already assigned row-value UPDATE/DELETE LIMIT
dynamic parity slice without adding WordPress-specific APIs, row-value
savepoint/window publication metadata, WAL/VFS, JSON, B-tree, PRAGMA,
trigger, or suite-runner behavior.
