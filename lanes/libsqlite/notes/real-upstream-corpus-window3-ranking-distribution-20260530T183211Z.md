# Real upstream corpus: window3 ranking distribution

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T183211Z-0`

Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`

Added focused PHP coverage in `SQLiteRealUpstreamWindow3RankingDistributionBatchTest.php` for the real upstream SQLite file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- `window3.test:1.0` 191-row `t2(a,b)` fixture
- `window3.test:1.1.3.1` `row_number() OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.4.1` `dense_rank() OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.4.3` `dense_rank() OVER (ORDER BY b RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.5.1` `rank() OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.5.3` `rank() OVER (ORDER BY b RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.7.1` `percent_rank() OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`
- `window3.test:1.1.8.1` `cume_dist() OVER (ORDER BY a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)`

Focused result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow3RankingDistributionBatchTest.php`
- `1 test files, 1338 assertions, 0 failures`

Non-overlap:

- This does not repeat the accepted `window4/windowE/windowfault/window1` frame batch recorded in `lane-status.json`.
- This ports a separate `window3.test` ranking/distribution slice over the full upstream 191-row corpus fixture and produces 1338 distinct TestRunner PASS cases.

Dependency closure:

- No new support component is needed. The batch reuses native `SQLiteWindowFunction` ranking and distribution helpers.
