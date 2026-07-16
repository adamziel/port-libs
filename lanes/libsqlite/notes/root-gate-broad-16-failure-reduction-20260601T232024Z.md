# Root Gate Broad 16 Failure Reduction

Session: `port-dev-sqlite-root-gate-16fail-20260601T232024Z`
Base accepted HEAD: `5af4ebdb05fbb2c88483b48bd1b1e1017dce0983`

## Scope

Reduced the broad `SQLiteHeaderTest.php` root-gate failure set by fixing two
current-source behavior failures without weakening assertions:

- `SQLiteSelectSql` now lets unnumbered anonymous `?` parameters consume
  zero-based caller values even when named parameters appear earlier in the SQL,
  while preserving explicit `?NNN` and named-parameter lookup. This fixes the
  mixed named/anonymous bind path used by `UPDATE ... FROM` SQL text plans.
- `SQLiteUpdateDeleteLimitPlan` now rejects negative `OFFSET` values whenever a
  `LIMIT` is present. Positive zero offsets remain valid.

## Evidence

Before edits:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9489 assertions, 16 failures
```

After edits:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9516 assertions, 14 failures
```

Focused behavior checks:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRootGateBroadFailureReductionTest.php
1 test files, 17 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php
1 test files, 250 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicNamedParameterSyntax20260531T155711ZTest.php
1 test files, 14420 assertions, 0 failures
```

## Non-overlap and Exclusions

I did not alter the JSON table subtype behavior, because local SQLite 3.51.2
confirms `json_each.value` preserves container JSON subtype when it is fed back
into JSON functions.

I did not add `lag()` / `lead()` zero or negative offset rejection. Local
SQLite 3.51.2 accepts those offsets, so the remaining root-gate expectation is
stale relative to current upstream behavior.

I did not change integer division or the stale grouped/compound/scalar-subquery
expectations. Local SQLite source-truth checks match the current port for those
remaining failures, so changing source behavior there would reduce parity.

## Dependency Closure

No new support component is needed. The patch reuses the existing
`SQLiteSelectSql` binder, `SQLiteUpdateFromSql` execution path, and
`SQLiteUpdateDeleteLimitPlan` validation.

## Next

The remaining broad failures should be split into expectation-rebase candidates
where local SQLite source truth already matches the port, and separate behavior
fixes only where a fresh current-upstream probe shows the port is wrong.
