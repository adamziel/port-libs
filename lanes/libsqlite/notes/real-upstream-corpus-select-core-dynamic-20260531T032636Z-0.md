# real-upstream-corpus-select-core-dynamic-20260531T032636Z-0

Added `SQLiteRealUpstreamSelect4CompoundDynamicTest.php` as an additive real
upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`

Owned upstream scenarios:

- `select4-1.0` distinct `log` rows from the upstream `t1(n,log)` corpus.
- `select4-1.1c` and `select4-1.1e` `UNION ALL` ordering over a filtered right arm.
- `select4-2.1` `UNION` duplicate elimination over the same arms.
- `select4-3.1.1` `EXCEPT` subtraction over the same arms.
- `select4-4.1.1` `INTERSECT` intersection over the same arms.

Focused coverage:

- 1 source/citation guard plus 5 canonical upstream rows plus 1,000 dynamic
  compound SELECT cases, each with result, count, first/last, fingerprint, and
  upstream-shape guards.
- Focused TestRunner growth: 1,006 PASS cases and 8,036 behavior
  assertions.

Non-overlap:

- This targets `select4.test` compound SELECT core behavior only. It does not
  repeat accepted `select8` LIMIT/OFFSET, `selectC` alias, `selectD` derived
  aggregate, grouped SELECT SQL text, subquery, comma-LIMIT, expression ORDER
  BY, JSON, WAL, VFS, B-tree, PRAGMA, trigger/FK, or suite-admission clusters.

Dependency closure:

- No new support component is required. This reuses the existing native
  `SQLiteSelectSql` executor and the hydrated upstream SQLite corpus.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicTest.php`
  passed: `1 test files, 8036 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  passed.
- `git diff --check -- lanes/libsqlite` passed.
