# real-upstream-corpus-btree-index-dynamic-20260601T024506Z-0

- Lane: `libsqlite`
- Base accepted HEAD: `c1c883c28f62d04121f13200bac2177a47c69bd4`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/skipscan2.test`
- Ported cluster: `skipscan2.test` sections `skipscan2-1.3` through `skipscan2-3.3eqp`, covering the optoverview people/height skip-scan threshold, explicit role-prefix rewrites, rowid versus WITHOUT ROWID secondary-index behavior, and WITHOUT ROWID primary-key `ANY(a) AND b=?` skip-scan planning.
- Focused assertion growth: `1003` new TestRunner PASS cases and `27007` assertions in `SQLiteRealUpstreamBtreeSkipscan2DynamicTest.php`.
- Status delta: `lanes/libsqlite/lane-status.json` `phpPass` moved from `5401392` to `5428399`; mapped denominator stays `1589 / 1589`.
- Non-overlap: owns `skipscan2.test` optoverview skip-scan behavior, avoiding the accepted `skipscan1.test` dynamic batch, generic planner skip-scan helper coverage, expression-index range-cost work, JSON/VFS/WAL accepted clusters, and B-tree page relocation/root-collapse/overflow-freelist clusters.
- Dependency closure: no new support component needed; the slice reuses the lane-local B-tree/index dynamic corpus planner for stat1 duplicate-count thresholds, explicit-prefix equivalent queries, rowid and WITHOUT ROWID storage shapes, and primary-key skip-scan evidence.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` => no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan2DynamicTest.php` => no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan2DynamicTest.php` => `1 test files, 27007 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeSkipscan2DynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `2 test files, 27010 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`
- `git diff --check -- lanes/libsqlite` => clean

Root harness: not run - isolated micro-slice.
