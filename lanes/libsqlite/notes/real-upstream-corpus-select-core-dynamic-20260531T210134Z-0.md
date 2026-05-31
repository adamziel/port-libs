# real-upstream-corpus-select-core-dynamic-20260531T210134Z-0

Micro-slice: `real-upstream-corpus-select-core-dynamic-20260531T210134Z-0`
Base accepted HEAD: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported scenarios:
  - `e_select-1.4.1`: cartesian product columns are left columns followed by right columns.
  - `e_select-1.4.2`: every unique left/right row combination is produced.
  - `e_select-1.4.3`: product row counts and output widths multiply/add.
  - `e_select-1.4.5`: comma, `JOIN`, `INNER JOIN`, and `CROSS JOIN` are equivalent for unconstrained joins.

## Behavior Delta

- Added `SQLiteRealUpstreamSelectCoreCartesianProductDynamic20260531T210134ZTest.php` with 1002 focused TestRunner PASS cases and 73011 focused assertions over dynamic `e_select.test` cartesian-product fixtures.
- Fixed `SQLiteSelectSql::selectProvidesColumn()` so annotated wildcard projections only claim the columns they actually expose. This lets `ORDER BY` over `SELECT *` joined sources add hidden order columns when the requested unqualified column is present in the pre-projection row but not visible after wildcard output collapsing.

## Red-First Evidence

- Before the source fix:
  - Command: `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreCartesianProductDynamic20260531T210134ZTest.php`
  - Result: `1 test files, 20011 assertions, 1000 failures`
  - Failure: `SQLite ORDER BY row is missing column c`

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreCartesianProductDynamic20260531T210134ZTest.php` passed.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` passed.
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreCartesianProductDynamic20260531T210134ZTest.php`
  - Result: `1 test files, 73011 assertions, 0 failures`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreCartesianProductDynamic20260531T210134ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelect2JoinSemanticsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSortCollation20260531T085917ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAliasOrderWhere20260531Test.php`
  - Result: `6 test files, 226054 assertions, 0 failures`
- `php -d memory_limit=2048M tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
  - Note: `SQLiteNoWordPressSpecificApiTest.php` is not present in this accepted worktree; the current generic API/domain guard was run instead.
- `git diff --check -- lanes/libsqlite` passed.

## Non-Overlap

This slice owns `e_select.test` `e_select-1.4.1` through `e_select-1.4.5` cartesian FROM/JOIN product semantics. It avoids already accepted SELECT subqueries, GROUP BY/HAVING text, expression ORDER BY, JSON table sources/constraints, NATURAL/LEFT/USING joins, and parenthesized join suites.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteSelectSql` row-array SELECT executor and the hydrated upstream SQLite source file only as source-truth evidence.

Root harness: not run - isolated micro-slice.
