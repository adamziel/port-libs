# real-upstream-corpus-btree-index-dynamic-20260601T012657Z-0

- Lane: `libsqlite`
- Base accepted HEAD: `b9bbeca66ecf5a12b5cede18d997f59a57398d59`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/skipscan1.test`
- Ported cluster: `skipscan1.test` sections `skipscan1-1.2` through `skipscan1-9.3`, plus the later duplicated empty-table, DISTINCT temp B-tree, repeated-column unique-index, and `max(a) WHERE a IN t1` regressions.
- Focused assertion growth: `1203` new TestRunner PASS cases and `26188` assertions in `SQLiteRealUpstreamBtreeSkipscan1DynamicTest.php`.
- Status delta: `lanes/libsqlite/lane-status.json` `phpPass` moved from `5224352` to `5250540`; mapped denominator stays `1589 / 1589`.
- Non-overlap: owns `skipscan1.test` skip-scan result/planner behavior; avoids accepted `whereG.test` likelihood skip-scan coverage, generic `SQLiteIndexSkipScanPlan` range-helper coverage, B-tree page relocation/root-collapse/overflow-freelist clusters, and accepted VFS/WAL storage slices.
- Dependency closure: no new support component needed; the slice reuses the lane-local B-tree/index dynamic corpus planner for stat1 selectivity, `ANY(...)` skip prefixes, PRIMARY KEY storage, `noskipscan` tokens, OR and IN-subquery skip-scan behavior, and DISTINCT temp B-tree evidence.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` => no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan1DynamicTest.php` => no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan1DynamicTest.php` => `1 test files, 26188 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan1DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereGLikelihoodPlannerDynamicTest.php lanes/libsqlite/tests/SQLiteIndexSkipScanBetweenCorpusTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `4 test files, 46348 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`
- `git diff --check -- lanes/libsqlite` => clean

Root harness: not run - isolated micro-slice.
