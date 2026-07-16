# root failures grouped order compound select coercion 20260602T0204Z

Status: ready for integration on base `237d5f4b8e36df3db6c68956f219939b05a1e90f`.

Behavior fixed:

- `SQLiteGroupedAggregate::summarize()` now preserves first-seen group order until an explicit `SQLiteGroupedAggregate::orderBy()` or SQL `ORDER BY` is applied. This fixes the broad grouped HAVING residual without weakening explicit grouped ordering.
- `SQLiteSelectCompound::union()` keeps boolean result presentation when duplicate-elimination compares `true`/`false` equal to integer `1`/`0`, while existing collated text duplicate behavior remains intact.
- `SQLiteSelectSql` mixed compound chains that include `UNION ALL` plus a distinct set operator no longer apply an implicit full set-order before final `ORDER BY`; equal final sort keys keep arm production order. Pure distinct set operators still retain upstream `select9.test` set-order behavior.

Source truth:

- Root-gate expectation reproduced from current-base `SQLiteHeaderTest.php`.
- Upstream set-operator order/LIMIT coverage guarded by `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test` through existing `SQLiteRealUpstreamSelect9SetOpsDynamicTest.php`.
- Upstream grouped aggregate behavior guarded by existing `SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php`.

Before:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9419 assertions, 9 failures
```

After:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9463 assertions, 5 failures
```

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRootFailureGroupedOrderCompoundSelectCoercionTest.php
1 test files, 12 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php lanes/libsqlite/tests/SQLiteCompoundSelectAffinityRecursiveOrderTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9SetOpsDynamicTest.php
3 test files, 8644 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php
1 test files, 4006 assertions, 0 failures
```

Expected dashboard movement: `phpPass +6` from the new focused regression test. `phpFail` can move `9 -> 5` for the broad current root gate once integrated. Mapped coverage remains `1589 / 1589`.

Dependency closure: no new support component is needed. This reuses the native grouped aggregate, compound set-operator, and parser-level SELECT SQL executor.

Non-overlap: this slice does not repeat WAL restart race coverage, JSON subtype cleanup, PDO invalid-DML parity, source-neutral STAT4 cleanup, accepted SELECT text/JOIN/GROUP BY/expression ORDER BY work, or broader select9/select3 corpus admission. It only fixes the current root grouped-order/compound-select coercion residual.
