# real-upstream-corpus-btree-index7-without-rowid-partial-stats-dynamic-20260531T050136Z

- Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`.
- Owned upstream range: `index7-1.1` through `index7-1.15`.
- Added behavior: WITHOUT ROWID partial-index lifecycle, `PRAGMA index_list` partial flags, partial-index predicate parse errors, `ANALYZE`/`sqlite_stat1` cardinality updates after UPDATE/DELETE, `REINDEX` preservation, and full-index addition alongside partial indexes.
- Non-overlap: this does not repeat accepted index7 later query-use rows, index6 rowid-table partial-index rows, indexA partial-affinity rows, bestindex virtual-table rows, index5 write-locality rows, B-tree page relocation, overflow freelist release, or root-collapse apply clusters.
- Focused PASS-line growth: `1203` new focused TestRunner PASS cases.
- Focused assertions: `24083`.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID partial-index lifecycle, stat1, reindex, and predicate-error helpers.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex7WithoutRowidPartialStatsDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex7WithoutRowidPartialStatsDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex7WithoutRowidPartialStatsDynamicTest.php
1 test files, 24083 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```

Root harness: not run - isolated micro-slice.
