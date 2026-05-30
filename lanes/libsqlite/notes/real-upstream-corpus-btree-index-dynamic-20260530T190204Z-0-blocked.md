# real-upstream-corpus-btree-index-dynamic-20260530T190204Z-0

Status: blocked by current-base overlap.

Assigned upstream domain: hydrated SQLite B-tree/index dynamic corpus from:

- `test/btree01.test` dynamic overflow balance scenarios `btree01-1.2.1` through `btree01-1.8.31`
- `test/btree02.test` cursor mutation scenario `btree02-110`
- `test/index.test` dynamic lookup lifecycle scenarios `index-4.2` through `index-4.12`
- `test/index5.test` sequential create-index write scenarios `index5-1.1` through `index5-1.3`
- `test/index6.test` partial-index join/update and regression scenarios `index6-7.0` through `index6-19.2`
- `test/index7.test` WITHOUT ROWID partial-index scenarios `index7-2.1` through `index7-2.104`
- `test/index9.test` bound partial-index scenarios `index9-1.1` through `index9-4.5`
- `test/indexA.test` rowid/WITHOUT ROWID partial-index affinity matrix sections `2.1` and `3.1`
- `test/indexedby.test` rowid affinity lookup scenarios `indexedby-11.2` through `indexedby-11.9`

Overlap found on base `28d061295d83cf4ef005caf2fa1b98587d6f90d3`: the exact assigned behavior is already represented by `lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` and `lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`, backed by `lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` and `lanes/libsqlite/src/SQLiteRealUpstreamBTreeIndexDynamicCorpus.php`.

Verification on this isolated worktree:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php
2 test files, 45420 assertions, 0 failures
```

This slice therefore cannot honestly add non-overlapping PASS-line growth without fabricating duplicate rows around already hydrated upstream scripts. Per the hard handoff floor, no small duplicate patch was emitted.

Next larger non-overlapping batch to try: move outside the already covered B-tree/index dynamic set into a real upstream B-tree/index family not present in the two current focused tests, preferably `test/autoindex*.test`, `test/bestindex*.test`, `test/indexexpr2.test`, `test/indexexpr3.test`, or `test/indexfault.test`. The next worker should first prove non-overlap against `SQLiteBTreeIndexDynamicCorpusPlanTest.php` and then batch at least 1,000 focused PASS cases or 5,000 behavior assertions from those real upstream files.

Dependency closure: no new support component was needed for this blocker note. Existing lane-local B-tree/index page, record, planner, partial-index, write-order, and cursor helpers already satisfy the overlapped assigned section.
