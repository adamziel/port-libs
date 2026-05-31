# real-upstream-corpus-pragma-schema-dynamic-20260531T151802Z-0

Base accepted HEAD: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-12.1`: `PRAGMA temp.table_info('abc')` stays pinned to temp and returns an empty rowset when only main has the table.
- `pragma-12.2`: `PRAGMA temp.default_cache_size = 200; PRAGMA temp.default_cache_size;`
- `pragma-12.3`: `PRAGMA temp.cache_size = 400; PRAGMA temp.cache_size;`

## Patch

- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicTempPager20260531Test.php`.
- Ports the temp-schema PRAGMA cluster into 1000 dynamic generic application variants plus one citation/non-overlap case.
- Covers schema-pinned temp introspection, unqualified main lookup after temp miss, table-valued attached schema lookup, `PRAGMA database_list` sequence/name preservation, and temp-only `default_cache_size`/`cache_size` mutation that does not mutate main or attached schema pager state.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTempPager20260531Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicTempPager20260531Test.php`
  - `1 test files, 26009 assertions, 0 failures`

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This owns `pragma.test` `pragma-12.1` through `pragma-12.3` only. It avoids accepted temp_store, cache_spill, pager settings main/aux, database_list, table_list, schema5, schema6, runtime-list, and prepared-expiry batches.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLiteAttachedSchemaCatalog` schema-qualified PRAGMA resolution and `SQLitePragmaPagerState` cache-size state.
