# real-upstream-corpus-select-core-dynamic-20260531T065130Z-0

Ported a bounded upstream `select4.test` core SELECT cluster into focused PHP
coverage.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- Sections: `select4-4.2`, `select4-7.2` through `select4-7.4`,
  `select4-6.4` through `select4-6.7`, and `select4-8.1` through `select4-8.2`.

## PHP Coverage

- Added `SQLiteRealUpstreamSelect4CompoundInDynamicTest.php`.
- Focused test count: `3611` TestRunner PASS cases.
- Focused assertion count: `44458` assertions.
- Behavior covered: compound SELECTs embedded in `IN` predicates, DISTINCT
  treatment of `NULL`, `UNION` / `EXCEPT` NULL distinctness, and text/numeric
  DISTINCT ordering from upstream `select4.test`.

## Non-Overlap

This slice owns the residual `select4.test` compound-subquery `IN` and
distinctness cluster. It does not repeat accepted grouped SELECT text, SELECT
subqueries from `subselect.test`, expression `ORDER BY`, JSON table SELECT
sources/cursors/constraints, `selectE`/`selectF` collation batches,
`selectC` alias batches, or prior `select4` compound top-level row batches.
Mapped denominator coverage remains unchanged because `select4.test` is
already represented in the hydrated upstream manifest.

## Dependency Closure

No new support component is needed. The tests reuse lane-local
`SQLiteSelectSql` compound SELECT, `IN` subquery, DISTINCT, and ORDER BY
execution.
