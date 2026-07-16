# real-upstream-corpus-btree-index5-write-order-dynamic-20260530T184928Z-0

Status: ready lane patch for real upstream B-tree/index corpus coverage.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
- Ported scenarios: `index5-1.1` through `index5-1.3`
- Behavior: `CREATE INDEX i1 ON t1(x)` over 100000 rows with 1024-byte pages records database `xWrite` page numbers and requires forward writes to dominate backward plus noncontiguous writes.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteOrderCases()`.
- Added `SQLiteBTreeIndexDynamicCorpusPlan::index5CreateIndexWriteOrderSummary()`.
- Added `SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php` with 1204 distinct TestRunner PASS cases and 13211 behavior assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex5WriteOrderDynamicTest.php`
  - `1 test files, 13211 assertions, 0 failures`
  - PASS-line delta: `+1204`

Non-overlap:

- This slice covers `index5.test` VFS write-order behavior during large CREATE INDEX.
- It does not repeat accepted B-tree index dynamic plan coverage, `index.test`, `index2`, `index4`, `index6`, `index8`, `index9`, `indexedby`, `btree01`, `btree02`, page relocation, root collapse, overflow freelist release, freeblock materialization, VFS file writer, VFS sync, rollback-journal commit/apply, or suite-evidence admission rows.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP B-tree/index corpus planning helpers and adds only deterministic upstream-derived write-order modeling.

Next task:

- Continue with a distinct hydrated upstream index/B-tree source such as `index3.test` string-identifier compatibility or `indexfault.test` failure behavior, avoiding `index5.test` write-order repeats.
