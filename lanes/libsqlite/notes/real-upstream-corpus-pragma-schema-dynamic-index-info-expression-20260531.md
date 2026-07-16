# real-upstream-corpus-pragma-schema-dynamic-index-info-expression-20260531

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T092300Z-0`
Base accepted HEAD: `c1dc98580d69cabeea0ebb72a1c7e33f357eaf2c`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma.test` sections `23.2b` through `23.2e` cover `PRAGMA index_xinfo` key rows, auxiliary rowid rows, DESC flags, COLLATE names, and expression-index terms with `cid = -2` and `name = NULL`.
- This slice ports the corresponding `PRAGMA index_info` key-row projection behavior for dynamic schema catalogs, including expression terms in middle and leading index positions.

Behavior change:

- `SQLitePragmaSchemaCatalog::indexInfo()` now uses parsed index term metadata, matching `indexXInfo()` for expression detection.
- Expression terms report `cid = -2` and `name = NULL` instead of a guessed source column or function token.
- Ordinary column terms still resolve their table column ids and names.

Focused evidence:

- Red-first check before the source fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexInfoExpression20260531Test.php` failed with `1 test files, 1755 assertions, 1000 failures`.
- After the fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexInfoExpression20260531Test.php` passed with `1 test files, 2505 assertions, 0 failures`.
- Related PRAGMA/schema family: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJoinXinfo20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicReloadCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexInfoExpression20260531Test.php` passed with `5 test files, 38748 assertions, 0 failures`.

Dashboard delta:

- Adds `1002` distinct focused TestRunner PASS cases.
- Adds `2505` behavior assertions in the new focused file.
- Updates lane-local `phpPass` from `2835919` to `2836921`.
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth inside already mapped upstream `pragma.test`.

Non-overlap:

- This does not repeat existing dynamic table-info, index-list, foreign-key, table-list, schema reload, generated-name, cache-spill, page/application, boolean-state, or join/xinfo corpus files.
- The new test namespace is `real upstream pragma schema dynamic index_info expression ...` and only targets expression-term projection through `PRAGMA index_info` and table-valued `pragma_index_info`.

Dependency closure:

- No new support component is needed.
- The slice reuses lane-local `SQLitePragmaSchemaCatalog` index term parsing already used by `PRAGMA index_xinfo`.

Root harness:

- Not run - isolated micro-slice.
