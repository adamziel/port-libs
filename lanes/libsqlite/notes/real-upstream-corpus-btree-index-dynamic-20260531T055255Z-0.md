# real-upstream-corpus-btree-index-dynamic-20260531T055255Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`

Ported sections:

- `index-7.1`: primary-key table population creates nineteen rows.
- `index-7.2`: lookup through the generated primary-key autoindex returns `f1=16` for `f2=65536`.
- `index-7.3`: `sqlite_master` exposes `sqlite_autoindex_test1_1` for the primary key.
- `index-7.4` and `index-7.5`: dropping the table removes the generated autoindex and preserves integrity.
- `index-8.1`: `DROP INDEX` on a missing index reports `no such index: index1`.
- `index-9.1` and `index-9.2`: `EXPLAIN CREATE INDEX` compiles without mutating `sqlite_schema`.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::indexPrimaryKeyDropExplainCases(1000)`
- `SQLiteBTreeIndexDynamicCorpusPrimaryKeyDropExplainTest.php`
- Focused file adds 1003 TestRunner PASS cases and 15677 assertions.

Non-overlap:

- This targets upstream `index.test` sections `index-7.1` through `index-9.2`, which are after the existing `index-1.1` through `index-6.5` dynamic schema lifecycle batch.
- It does not repeat accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, `index2`, `index3`, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexedby`, `btree02`, `bestindex*`, or `where*` planner batches.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner, schema catalog, primary-key autoindex, drop-index diagnostics, and EXPLAIN non-mutation modeling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPrimaryKeyDropExplainTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPrimaryKeyDropExplainTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
