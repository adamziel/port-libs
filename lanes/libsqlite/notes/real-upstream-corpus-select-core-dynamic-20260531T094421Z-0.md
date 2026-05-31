# real-upstream-corpus-select-core-dynamic-20260531T094421Z-0

Base accepted HEAD: `ffcc95ebfcac7bbcd16b24facd07c90559f1565a`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`

Ported sections:

- `e_select-0.3`: result-column expressions accept a bare alias without `AS`,
  including the upstream shape `SELECT 'x'||a||'x' alias FROM t1`.
- `select6-9.10`: a limited derived SELECT aliases a scalar subquery with
  the upstream bare-alias form `(SELECT 10+x) y`.

## Behavior Delta

`SQLiteSelectSql::expressionAlias()` now recognizes top-level bare projection
aliases when the trailing token is a non-reserved identifier. It deliberately
does not split expression suffixes such as `COLLATE rtrim`, `IS NULL`, `NOT
NULL`, `ESCAPE`, or unfinished binary expressions.

Added `SQLiteRealUpstreamSelectImplicitAliasDynamic20260531Test.php` with:

- 1 upstream source-citation test.
- 1,000 dynamic behavior tests that each execute both an `e_select-0.3`
  concatenation bare-alias SELECT and the exact `select6-9.10` scalar-subquery
  bare-alias derived SELECT shape.
- 1 non-overlap/dependency note test.

Focused movement: `1,002` TestRunner PASS cases and `11,009` assertions.
Mapped denominator movement is unchanged because both upstream files are
already represented in the hydrated manifest.

## Non-Overlap

This owns the exact implicit projection alias parser behavior omitted by prior
accepted batches. It avoids AS-alias result-column batches, `select6` explicit
`AS` derived LIMIT coverage, grouped SELECT text, expression `ORDER BY`, JSON
table source/cursor/constraint coverage, B-tree, WAL, VFS, and metadata-only
runner rows.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectImplicitAliasDynamic20260531Test.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectImplicitAliasDynamic20260531Test.php`
  - `1 test files, 11009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T060517ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6LimitDerivedDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1ColumnNamesDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHSchemaCompoundDynamicTest.php`
  - `4 test files, 19379 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component is needed. This reuses the
existing `SQLiteSelectSql` projection parser, scalar subqueries, derived-table
execution, join row production, and `ORDER BY` alias resolution.
