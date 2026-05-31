# real-upstream-corpus-btree-index-dynamic-20260531T154041Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus
batch from the hydrated SQLite checkout.

- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/fordelete.test`.
- Upstream sections covered: `fordelete-1.1` through `fordelete-2.5`.
- Focus: DELETE `OpenWrite` btree flag behavior, including which table and
  index btrees are opened with `BTREE_FORDELETE`, when table payload reads
  suppress the flag, rowid-delete secondary-index handling, and OR-optimized
  delete rowlist handling.
- PHP behavior added:
  `SQLiteBTreeIndexDynamicCorpusPlan::forDeleteOpenWriteFlagCases(1200)` plus
  `SQLiteRealUpstreamBtreeForDeleteOpenWriteDynamicTest.php`.
- Focused PHP movement: `1203` distinct TestRunner PASS cases with `35327`
  behavior assertions in the new focused file.

Non-overlap:

- This owns upstream `fordelete.test` `OpenWrite`/DELETE flag analysis only.
- It does not repeat accepted `delete.test`, `delete2.test`, `delete3.test`,
  `delete4.test`, `where*`, `index*`, `indexedby`, `bestindex*`, B-tree page
  relocation/root-collapse/interior-merge/overflow-freelist/freeblock release,
  JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, or source-neutral cleanup
  clusters.
- Count type: selected PHP PASS-line growth only. Mapped upstream denominator
  remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeForDeleteOpenWriteDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeForDeleteOpenWriteDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeForDeleteOpenWriteDynamicTest.php`
  - `1 test files, 35327 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeForDeleteOpenWriteDynamicTest.php`
  - `2 test files, 100495 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index
  dynamic corpus planner and existing DELETE open-write flag modeling.

Root harness:

- Not run - isolated micro-slice.
