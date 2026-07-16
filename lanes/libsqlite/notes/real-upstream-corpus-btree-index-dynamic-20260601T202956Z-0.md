# real-upstream-corpus-btree-index-dynamic-20260601T202956Z-0

Status: ready for focused integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid4.test`
- Upstream sections covered: `without_rowid4-1.1.1` through `without_rowid4-1.6.3`.

Patch:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid4TriggerOrderCases()`.
- Added `SQLiteRealUpstreamWithoutRowid4TriggerDynamicTest.php` with 1,200
  dynamic behavior cases plus source-truth, source-range, invalid-size, and
  dependency-closure checks.

Focused behavior:

- WITHOUT ROWID row-trigger order for six upstream table declarations, including
  main/temp tables, `INTEGER PRIMARY KEY`, ordinary primary keys, and secondary
  index variants.
- BEFORE and AFTER UPDATE row triggers observe the exact old/new values and
  table aggregate visibility from upstream `without_rowid4-1.*.1`.
- BEFORE and AFTER DELETE row triggers observe pre-delete, post-row-delete, and
  empty-table aggregate visibility from upstream `without_rowid4-1.*.2`.
- BEFORE and AFTER INSERT row triggers observe empty-table and inserted-row
  aggregate visibility from upstream `without_rowid4-1.*.3`.
- Recursive triggers remain disabled for this upstream file.

Non-overlap:

- Owns only upstream `without_rowid4.test` section 1 trigger-order behavior.
- Avoids accepted `without_rowid2.test` foreign-key catalog coverage,
  `without_rowid5.test` requirement coverage, `without_rowid1/6/7` primary-key
  and secondary-index tail coverage, `where*`, `wherelimit*`, `index*`,
  `autoindex*`, `bestindex*`, JSON, WAL, VFS, PRAGMA, trigger/fkey dynamic
  batches, row-value, SELECT, and source-neutral cleanup batches.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid4TriggerDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid4TriggerDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid4TriggerDynamicTest.php`
  - `1 test files, 72215 assertions, 0 failures`
  - Adds 1,204 focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid4TriggerDynamicTest.php`
  - `2 test files, 137385 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Status delta:

- Expected `phpPass`: `6250656 -> 6251860` (`+1204`).
- Mapped coverage remains `1589 / 1589`.
- Broad full-lane/release parity remains blocked by the existing 16 broad
  failures; root harness was not run from this isolated micro-slice.

Dependency closure:

- No new support component is needed.
- Reuses the lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID table
  metadata, and trigger event-order evidence arrays.

Next task:

- Continue non-overlapping B-tree/index real-corpus coverage with
  `wherefault.test`, `wherelfault.test`, or `without_rowid3.test`, or pivot to
  a source behavior fix if those expose a current executor/parser blocker.
