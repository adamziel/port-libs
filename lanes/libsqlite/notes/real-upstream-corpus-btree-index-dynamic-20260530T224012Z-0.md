Real upstream corpus B-tree/index dynamic slice

- Base accepted HEAD: dc9a740fd34e07dba61e9143b3604d183ad170bf.
- Added `SQLiteRealUpstreamBtreeIndexDynamicTest.php` with 1,001 focused TestRunner PASS cases and 10,001 assertions.
- Upstream sources: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test` (`index-4.1` through `index-4.13`, `index-6.1` through `index-6.4`) and `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree01.test` (`btree01-2.1`).
- Non-overlap: exercises index-leaf record replacement, freed-cell reuse, sorted record preservation, and overflow-chain release through `SQLiteBTreeIndexMutationCurrent`; it does not repeat page relocation, root collapse, overflow freelist release, VFS/WAL, JSON, or SELECT corpus slices.
- Focused verification: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicTest.php`; `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexDynamicTest.php` => `1 test files, 10001 assertions, 0 failures`.
- Guard verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 3 assertions, 0 failures`.
- Dependency closure: no new support component needed; the slice reuses existing index leaf, record, overflow page, and B-tree page header helpers.
