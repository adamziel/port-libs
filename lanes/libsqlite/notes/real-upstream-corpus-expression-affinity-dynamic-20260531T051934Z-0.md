# real-upstream-corpus-expression-affinity-dynamic-20260531T051934Z-0

Implemented a focused real-upstream expression/affinity corpus shard from
SQLite `test/affinity3.test` sections `affinity3-100` through `affinity3-142`.

Coverage added:

- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicAffinity3RealTest.php`
- 80 REAL-column source rows with INTEGER primary keys.
- 15 REAL-affinity predicate forms per source row: equality, range,
  `BETWEEN`, `NOT BETWEEN`, `IN`, `NOT IN`, combined `AND`/`OR`, and
  `CAST(... AS REAL)` RHS equality.
- 1,200 distinct TestRunner PASS cases, backed by the hydrated upstream
  `affinity3.test` file and a local `sqlite3` oracle.
- 35,206 focused assertions over selected rowids, selected counts, and REAL
  storage class preservation through `SQLiteSelectSql`.

Non-overlap:

- Does not repeat accepted REAL arithmetic overflow, `expr2` REAL operator,
  `types2` comparison-affinity, REAL `IN` ticket, CAST-prefix, COLLATE postfix,
  Unicode GLOB, or date-affinity shards.
- This shard specifically owns dynamic REAL-column comparison behavior from
  upstream `affinity3.test`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicAffinity3RealTest.php`
  - `1 test files, 35206 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicAffinity3RealTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed
- `SQLiteNoWordPressSpecificApiTest.php`
  - not present in this worktree

Dependency closure:

- No new support component needed. The shard reuses `SQLiteSelectSql`,
  column-affinity metadata, expression predicate dispatch, and the local
  `sqlite3` oracle for upstream parity.
