# real-upstream-corpus-select-core-dynamic-20260531T115945Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `e_select-4.1`: aggregate SELECT result columns may combine aggregate functions with wildcard source projection.
- `e_select-4.7.2`: aggregate SELECT over an empty joined input still returns one row, and wildcard source columns expand as NULL.

Behavior ported:

- `SQLiteSelectSql` now preserves wildcard result terms through implicit aggregate rewriting instead of treating them as aggregate summary internals.
- Wildcard annotations include joined right-source columns, so empty aggregate joins can project the complete source shape.
- `SQLiteSelectQuery` materializes implicit aggregate wildcard source columns from the first input row, or from the row that supplies a top-level `min()` / `max()` value, and uses NULLs for empty inputs.
- `SQLiteSelectProjection` projects aggregate wildcards only from annotated source columns, avoiding aggregate summary bookkeeping columns.
- `ORDER BY` ordinal resolution now maps ordinals inside an expanded wildcard to the corresponding result column, preserving adjacent compound-subquery wildcard ordering.

Focused coverage:

- New test file: `SQLiteRealUpstreamESelectAggregateWildcardDynamic20260531T115945ZTest.php`
- Adds 1002 focused TestRunner PASS cases:
  - 1 hydrated-source citation case.
  - 1000 dynamic `e_select-4.1` / `e_select-4.7.2` aggregate wildcard cases.
  - 1 non-overlap and dependency-closure note case.
- Focused assertion count for the new file: 24010 assertions.
- Expected `phpPass` delta: `+1002` from `2902665` to `2903667`.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is behavior depth over already mapped upstream SELECT inventory.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectAggregateWildcardDynamic20260531T115945ZTest.php`
  - `1 test files, 24010 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectEmptyAggregateDynamic20260531T100625ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1RepeatedWildcardDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicWhereOrderTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `5 test files, 81747 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectDParenthesizedTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDParenthesizedJoinDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBCompoundSubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectBDerivedCompoundDynamicTest.php`
  - `5 test files, 54827 assertions, 0 failures`
- `php -l` on changed PHP source/test files
  - `SQLiteSelectSql.php`, `SQLiteSelectQuery.php`, `SQLiteSelectProjection.php`, and `SQLiteRealUpstreamESelectAggregateWildcardDynamic20260531T115945ZTest.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Non-overlap:

This slice owns only `e_select.test` aggregate wildcard result-column expansion and empty-input wildcard NULL expansion. It avoids accepted empty aggregate scalar-column behavior, DISTINCT/ALL, LIMIT datatype, compound ORDER BY arm-resolution, join/source wiring, grouped SELECT text, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows.

Dependency closure:

No new support component is needed. This reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, `SQLiteSelectProjection`, and the hydrated upstream SQLite SELECT corpus.
