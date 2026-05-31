# real-upstream-corpus-btree-index-dynamic-20260531T041756Z-0

- Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`.
- Source truth: hydrated upstream SQLite `test/index.test`, sections `index-1.1` through `index-6.5`.
- Owned behavior: early `CREATE INDEX` catalog lifecycle, database reopen schema persistence, dependent-index cleanup on `DROP TABLE`, missing table/column errors, rejected `sqlite_master` indexing, duplicate index/table-name validation, and integrity-preserving cleanup.
- Non-overlap: does not repeat existing `index-4` lookup/create-drop cases, `index-10` through `index-23` late lifecycle cases, `index19` conflict-policy cases, `index5` VFS write-order cases, `indexA`, `index8`, `index9`, autoindex, where, page-move, overflow freelist, or accepted B-tree/VFS/WAL clusters.
- Focused TestRunner movement: new file adds `1203` TestRunner PASS cases (`1200` dynamic upstream cases plus corpus count, invalid-size guard, and dependency-closure note), moving `lanes/libsqlite/lane-status.json` `phpPass` from `2025275` to `2026478`.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexEarlySchemaLifecycleDynamicTest.php` passed.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexEarlySchemaLifecycleDynamicTest.php` passed: `1 test files, 18011 assertions, 0 failures` with `1203` PASS lines.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
  - `git diff --check -- lanes/libsqlite` passed for tracked lane changes.
  - Added-line source scan found no new WordPress/wp-shaped source text.
- Dependency closure: no new support component needed; the slice reuses lane-local B-tree/index dynamic corpus planning, schema catalog validation, CREATE INDEX error modeling, reopen persistence, and drop-table cleanup helpers.
- Root harness: not run; isolated micro-slice.
