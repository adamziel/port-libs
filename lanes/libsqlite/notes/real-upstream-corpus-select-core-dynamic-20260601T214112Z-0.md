# real-upstream-corpus-select-core-dynamic-20260601T214112Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260601T214112Z-0`

Added `SQLiteRealUpstreamSelect4CompoundColumnNamesDynamic20260601T214112ZTest.php`, a focused real upstream SELECT-core dynamic corpus batch backed by:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `select4-9.1` through `select4-9.8`: compound SELECT result column names come from the left-most arm even when later arms use different aliases.
- `select4-9.9.1` through `select4-9.12`: derived compound subqueries expose those inherited names through `SELECT *` expansion and `WHERE` filtering.

Focused growth:

- Adds 1,002 distinct TestRunner PASS cases: one upstream source citation, 1,000 dynamic compound-name/derived-filter cases, and one non-overlap/dependency summary.
- Each dynamic case runs the upstream `select4-9.1` through `select4-9.12` shapes with varied integer values and verifies rows, flat values, inherited result-column names, row counts, and result-shape fingerprints.

Non-overlap:

- Existing SELECT-core coverage already owns select4 compound row-set operations, CTAS materialization, VALUES compound arms, coroutine/yield preservation, aggregate subquery joins, and aggregate-compound pushdown.
- This slice owns only the `select4-9.*` compound result-column inheritance and derived `WHERE` filtering cluster.
- It avoids select1 result-column naming, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, source-neutral cleanup, and denominator metadata movement.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundColumnNamesDynamic20260601T214112ZTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundColumnNamesDynamic20260601T214112ZTest.php` - 1 test files, 66010 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4AggregateJoinDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundAggregatePushdownDynamic20260601T011723ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundColumnNamesDynamic20260601T214112ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundInDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4OrderByScalarSubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4ValuesCompoundDynamicTest.php` - 9 test files, 226772 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - 1 test files, 8 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` - clean.
- Example smoke: not run; no example was added or updated for this generic upstream SELECT-core corpus slice.

Dependency closure:

- No new support component is needed. The batch reuses the existing native `SQLiteSelectSql` compound SELECT, derived table, `SELECT *`, `ORDER BY`, and `WHERE` predicate execution paths.

Root harness:

- Not run - isolated micro-slice.
