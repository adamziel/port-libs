# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T182027Z-0

Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Scenario family: `expr-1.58` through `expr-1.99`, covering NULL
  propagation through arithmetic, comparison, boolean `AND` / `OR`,
  `coalesce()`, `BETWEEN` / `NOT BETWEEN`, and bitwise/shift expressions.

## Delta

- Added `SQLiteRealUpstreamExprNullBetweenBulkTest.php` with `1,041`
  distinct focused TestRunner PASS cases and `1,043` assertions.
- Fixed `SQLiteSelectSql` so value-expression parsing supports top-level
  `AND` / `OR` and `BETWEEN` / `NOT BETWEEN`, and so `<<` is not consumed as a
  `<` comparison.
- Mapped coverage is unchanged at `1189 / 1589`. Count this handoff as
  PASS-line growth only, not mapped-denominator growth.

## Verification

- Initial focused red run:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExprNullBetweenBulkTest.php`
  - `1 test files, 603 assertions, 440 failures`
  - Missing behavior: value-expression `AND` / `OR`, `BETWEEN`, and `<<`
    tokenization.
- After fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExprNullBetweenBulkTest.php`
  - `1 test files, 1043 assertions, 0 failures`

## Non-overlap

This slice avoids fabricated veryquick `next965-980` metadata, prior
`e_expr.test` concatenation and precedence bulk slices, date/affinity dynamic
coverage, and mapped denominator burnup rows. It exercises real upstream
`expr.test` behavior through the PHP SELECT executor.

## Dependency closure

No new support component is needed. The existing bounded PHP SELECT SQL parser,
predicate evaluator, and expression evaluator are reused.
