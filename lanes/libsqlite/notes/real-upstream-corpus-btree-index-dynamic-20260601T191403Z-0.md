# real-upstream-corpus-btree-index-dynamic-20260601T191403Z-0

Status: ready for focused integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid2.test`
- Upstream sections covered: `without_rowid2-1.0` through
  `without_rowid2-3.5`.

Patch:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::withoutRowid2ForeignKeyCatalogCases()`.
- Added `SQLiteRealUpstreamWithoutRowid2ForeignKeyDynamicTest.php` with 1200
  dynamic behavior cases plus source-truth, source-range, invalid-size, and
  dependency-closure checks.
- Updated `lane-status.json` for the verified focused PASS delta.

Focused behavior:

- WITHOUT ROWID parent/child table creation with self, external, and composite
  foreign-key references.
- Child table drop cleanup for rowid children that reference a WITHOUT ROWID
  parent.
- Exact `PRAGMA foreign_key_list` rows for explicit and implicit parent columns,
  including composite `ON UPDATE SET NULL` / `SET DEFAULT` plus `ON DELETE
  CASCADE` actions.
- `DBSTATUS_DEFERRED_FKS` remains clear after the schema-only setup.

Non-overlap:

- Owns only upstream `without_rowid2.test` foreign-key catalog behavior.
- Avoids accepted `without_rowid5.test` requirements,
  `without_rowid1.test`/`without_rowid6.test`/`without_rowid7.test`
  primary-key-tail and redundant-key coverage, `wherelimit*`, `index*`,
  `where*`, `bestindex*`, JSON, WAL, VFS, PRAGMA, trigger, row-value, SELECT,
  and source-neutral cleanup batches.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid2ForeignKeyDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid2ForeignKeyDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid2ForeignKeyDynamicTest.php`
  - `1 test files, 47099 assertions, 0 failures`
  - Adds 1204 focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid2ForeignKeyDynamicTest.php`
  - `2 test files, 112269 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed

Status delta:

- Expected `phpPass`: `6188474 -> 6189678` (`+1204`).
- Mapped coverage remains `1589 / 1589`.
- Broad full-lane/release parity remains blocked by the existing 16 broad
  failures; root harness was not run from this isolated micro-slice.

Dependency closure:

- No new support component is needed.
- Reuses the lane-local B-tree/index dynamic corpus planner, WITHOUT ROWID
  table metadata, foreign-key catalog row modeling, child-drop cleanup, and
  `DBSTATUS_DEFERRED_FKS` evidence.

Next task:

- Continue non-overlapping B-tree/index real-corpus coverage with
  `without_rowid4.test`, `wherefault.test`, or `wherelfault.test`, or pivot to
  a source behavior fix if those expose a current executor/parser blocker.
