# real-upstream-corpus-btree-index-dynamic-20260530T174436Z-0

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T174436Z-0`
- Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`
- Added upstream-backed B-tree/index dynamic corpus coverage in `SQLiteBTreeIndexDynamicCorpusPlan` and `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`
  - `index4-1.1` through `index4-1.8`: large `CREATE INDEX` builds over randomblob rows, cache-limited index build, large mixed blob/text/NULL keys, one-row index build, and empty-table index build.
  - `index4-2.2`: duplicate-key `CREATE UNIQUE INDEX` failure preserves table rows and leaves integrity clean.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index8.test`
  - `index8-1.0` and `index8-1.1`: `ORDER BY a,b LIMIT 2` should use `t1abc` when the index covers the `c=4` filter, but should not use `t1abd` when it does not cover the filter.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test`
  - `btree01-2.1` and `btree01-2.2`: `WITHOUT ROWID` overflow payload lookup returns the same rows through LEFT and RIGHT join probes.

Focused evidence:

- First red-focused run exposed one over-strict assertion for `index4-1.6`, where 256 rows with 5202-byte blob keys still require external sort pressure. The assertion was corrected to key off large row counts and empty/single-row cases without weakening upstream row expectations.
- Final focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- Result: `1 test files, 2749 assertions, 0 failures`.
- New upstream-backed PASS cases in the edited focused file: 24 additive cases for `index4.test`, `index8.test`, and `btree01.test` 2.x.

Non-overlap:

- Does not repeat accepted B-tree page relocation, root collapse, overflow freelist release, bulk overflow freeblocks, index-interior merge, PRAGMA index metadata, expression-index range-cost, SQL expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS transaction application, or existing `btree01-1.x`, `btree02-110`, `index6`, `index9`, and `indexedby` coverage.
- This slice only adds distinct real upstream behavior from `index4.test`, `index8.test`, and the `btree01.test` overflow join regression.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local B-tree page/payload sizing, index-planner, and corpus plan helpers.
