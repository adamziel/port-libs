# real-upstream-corpus-select-core-dynamic-20260531T074215Z-0

Added `SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `select4-1.1d` / `select4-1.1e`: `CREATE TABLE AS` materializes ordered `UNION ALL` rows, then `SELECT * FROM t2` reads the stored order back.
- `select4-3.1.2` / `select4-3.1.3`: `CREATE TABLE AS` materializes ordered `EXCEPT` rows, then `SELECT * FROM t2` reads the stored order back.

Focused assertion/PASS movement:

- Adds `1000` dynamic TestRunner cases plus `2` source/dependency citation cases.
- Each dynamic case runs four upstream-shaped compound SELECTs through `SQLiteSelectSql` and verifies the materialization/readback boundary for the resulting generic table image.
- Expected selected PASS-line growth: `+1002`.

Non-overlap:

- The latest accepted select4 batch owns direct compound set-operator rows and `IN` subquery membership. This slice owns the separate upstream materialized `CREATE TABLE AS ... SELECT` readback shape from `select4-1.1d/e` and `select4-3.1.2/3.1.3`.
- It does not repeat accepted `select1` projection/wildcard batches, `select2` WHERE expression/range batches, `select3` aggregate batches, `select5` aggregate/limit batches, `select6` derived-table batches, `selectA`/`selectB` compound-collation/subquery batches, `selectC` alias/distinct-derived batches, `selectD` parenthesized/derived join batches, `selectE`/`selectF` compound collation/copy batches, `selectG` VALUES batches, `selectH` omit-unused/wide-compound batches, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select4.test` is already part of the hydrated upstream manifest coverage.

Dependency closure:

- No new support component is needed. The shard reuses `SQLiteSelectSql` compound SELECT execution and represents the upstream CTAS boundary as a generic materialized PHP table image for the readback assertion.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php`
  - `1 test files, 10009 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: focused guard file is absent in this worktree (`Focused path does not exist in repository`).
