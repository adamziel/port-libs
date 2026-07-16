# real-upstream-corpus-pragma-schema-dynamic-20260531T065625Z-0

- Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test` sections `1.0`, `2.0`, `3.0`; `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test` sections `4.2` through `4.5` and `6.0` through `7.3`.
- Behavior: table-valued PRAGMA virtual tables now expose SQLite-style hidden `arg` and `schema` columns through `PRAGMA table_xinfo(pragma_*)`, while `PRAGMA table_info(pragma_*)` remains visible-column only.
- New focused coverage: `SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php` adds `2221` distinct TestRunner PASS cases and `17683` behavior assertions.
- Non-overlap: this does not repeat existing function/module/pragma list visible shape tests, PRAGMA schema DDL reparse, index_xinfo join matrices, or accepted PRAGMA result-shape batches. It targets the previously missing hidden argument columns on table-valued pragma module schemas.
- Dependency closure: no new support component is needed; the existing `SQLitePragmaSchemaCatalog` schema introspection component is extended.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php` passed.
- Red-first focused run before the source filter fix: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php` failed with `1 test files / 9962 assertions / 1547 failures` because `table_info()` exposed hidden columns.
- Focused passing run after the fix: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php` passed with `1 test files / 17683 assertions / 0 failures`.
- Adjacent guard: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPragma5VirtualRowsTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVirtualShape20260531Test.php` passed with `4 test files / 57757 assertions / 0 failures`.
- `SQLiteNoWordPressSpecificApiTest.php` is absent in this worktree.
