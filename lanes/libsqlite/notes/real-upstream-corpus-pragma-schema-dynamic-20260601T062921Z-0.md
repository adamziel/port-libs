# real-upstream-corpus-pragma-schema-dynamic-20260601T062921Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Upstream sections ported: `pragma-23.2a`, `pragma-23.2b`, `pragma-23.2c`, `pragma-23.2d`, `pragma-23.2e`, `pragma-23.3`, `pragma-23.4`, and `pragma-23.5`.

## Handoff Delta

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexReload20260601Test.php`.
- Adds 1,251 focused TestRunner PASS cases and 8,506 focused assertions.
- Behavior covered: second-connection PRAGMA catalog reload after `i2` is rebuilt, `index_xinfo` key/rowid auxiliary/expression/collation/DESC metadata, `index_list` ordering after `i3` is recreated, `table_info` visibility after `ALTER TABLE ADD COLUMN e`, and `foreign_key_list` visibility after `t2` is dropped and recreated with `y REFERENCES t1`.
- Non-overlap: this owns `pragma.test` 23.2a-23.5 index/table/foreign-key PRAGMA reloads only; it avoids accepted `data_version`, `cache_spill`, `temp_store`, `table_list`, `pragma5` virtual rows, `schema3` refresh, `schema4` namespace, `schema5` legacy, `schema6` equivalence, `trusted_schema`, JSON, WAL, VFS, B-tree, and SELECT clusters.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexReload20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexReload20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicIndexReload20260601Test.php`
  - `1 test files, 8506 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php -r '$json = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid JSON\n";'`
  - `lane-status.json valid JSON`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency Closure

No new support component needed; this reuses the lane-local `SQLitePragmaSchemaCatalog` PRAGMA metadata implementation.

## Root

Not run - isolated micro-slice.
