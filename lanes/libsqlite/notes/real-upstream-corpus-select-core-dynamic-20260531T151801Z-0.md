# real-upstream-corpus-select-core-dynamic-20260531T151801Z-0

Lane: `libsqlite`
Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported section: `e_select-4.11`
- Upstream requirement: `R-10470-30318`, GROUP BY expressions use the normal SQLite collation selection rules.
- Specific upstream SQL shapes cited in the test: `SELECT count(*) FROM b3 GROUP BY a`, `SELECT count(*) FROM b3 GROUP BY +a`, and `SELECT count(*) FROM b3 GROUP BY a||''`.

## Behavior

This patch fixes grouped aggregation so GROUP BY keys are built and sorted with the collation selected by the grouping expression:

- a source column with `NOCASE` metadata groups case variants together;
- unary plus preserves the operand column's collation for grouping;
- a binary expression such as concatenation falls back to BINARY and keeps case variants distinct;
- explicit `COLLATE binary` / `COLLATE nocase` overrides the source-column collation.

Pre-fix local probe on this worktree showed `SELECT count(*) FROM b3 GROUP BY a` over a `NOCASE` source column returning four binary groups instead of SQLite's two folded groups.

## Files

- `lanes/libsqlite/src/SQLiteSelectExpression.php`
- `lanes/libsqlite/src/SQLiteGroupedAggregate.php`
- `lanes/libsqlite/src/SQLiteSelectQuery.php`
- `lanes/libsqlite/src/SQLiteSelectSql.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php`

## Coverage And Non-Overlap

- Added `SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php`.
- New focused PASS growth: `1002` distinct TestRunner PASS cases.
- New focused behavior assertions: `19010`.
- Mapped coverage remains `1589 / 1589`; this is behavior/PASS growth against an already mapped upstream script.
- Non-overlap: avoids accepted e_select DISTINCT/ALL, empty aggregate, aggregate wildcard, compound ORDER, LIMIT datatype, e_select2 join associativity/collation/subquery, grouped SELECT text, expression ORDER BY, JSON table, WAL, VFS, B-tree, and metadata-only runner rows.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php && php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php && php -l lanes/libsqlite/src/SQLiteSelectQuery.php && php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php`
  - all changed PHP files: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php`
  - `1 test files, 19010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectDistinctAllDynamic20260531T103105ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectEmptyAggregateDynamic20260531T100625ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectAggregateWildcardDynamic20260531T115945ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectCompoundOrderResolutionDynamic20260531T111241ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinAssociativityDynamic20260531T124249ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect5AggregateDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `10 test files, 215407 assertions, 0 failures`.

## Dependency Closure

No new support component is required. The slice reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, `SQLiteSelectExpression`, and hydrated upstream SQLite `e_select.test` source truth.

## Next

Continue SELECT-core dynamic coverage on a distinct upstream section only. Good follow-ups are remaining e_select GROUP BY/HAVING edges not already covered by the aggregate wildcard/empty aggregate batches, or broader SELECT planner/executor behavior that does not repeat this GROUP BY collation fix.
