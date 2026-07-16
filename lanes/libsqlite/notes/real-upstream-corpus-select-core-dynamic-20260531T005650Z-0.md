# real-upstream-corpus-select-core-dynamic-20260531T005650Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`

## Ported behavior

- `selectG-100`: dynamic `VALUES` lists used as row sources, with `count(x)`,
  `sum(x)`, `avg(x)`, `ORDER BY`, `LIMIT`, and `OFFSET` checks.
- `selectG-110` / `selectG-120`: scalar `SELECT (VALUES ...)` behavior that
  returns only the left-most row from a multi-row `VALUES` expression.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectGValuesDynamicTest.php`
  - `1 test files, 6754 assertions, 0 failures`
  - `2251` distinct TestRunner PASS cases.

## Non-overlap

This slice owns the residual `selectG.test` large/dynamic `VALUES` cluster. It
does not repeat accepted `select1` through `select9`, `selectA` through
`selectF`, `selectH`, expression `ORDER BY`, grouped SELECT text, subquery,
JSON table source/cursor/constraint, or metadata-only runner evidence.

Mapped denominator coverage remains unchanged because `selectG.test` is already
present in the hydrated upstream manifest coverage.

## Dependency closure

No new support component is needed. The batch reuses the existing
`SQLiteSelectSql` `VALUES` parser/executor, aggregate dispatch, scalar
expression handling, and row-array ORDER/LIMIT pipeline.
