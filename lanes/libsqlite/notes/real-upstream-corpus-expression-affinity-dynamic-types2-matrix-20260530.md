# Real Upstream Corpus Expression Affinity Dynamic Types2 Matrix

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T195143Z-0`

Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- Scenario family: `types2-2.*`, `types2-3.*`, and `types2-4.*` comparison-affinity row selection over `CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY)`.

Behavior added:

- Added 3744 focused PHP TestRunner cases for INTEGER, NUMERIC, and TEXT declared-affinity WHERE comparisons over broad numeric and text-literal matrices.
- Fixed row-array SELECT predicate comparison affinity by honoring optional per-row `__sqlite_column_affinities` metadata for column operands.
- Fixed TEXT affinity formatting of REAL values so inserted `10.0` remains text `10.0`, matching SQLite comparison behavior.

Non-overlap:

- Existing `SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php` owns 1000 selected cases for INTEGER/NUMERIC/no-affinity predicates and intentionally left TEXT-affinity metadata as a follow-up.
- Existing `SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php` owns the broad no-affinity `o` column matrix.
- This slice owns declared-affinity metadata plus broad INTEGER/NUMERIC/TEXT row-selection matrices, including leading-zero text numerals and TEXT-affinity real formatting.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php`
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php` -> `1 test files, 3744 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php` -> `4 test files, 7402 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> passed

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded row-array SELECT executor, expression-affinity helpers, and local `sqlite3` oracle used by existing real upstream expression-affinity tests.
