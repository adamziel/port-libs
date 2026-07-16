# real-upstream-corpus-pragma-schema-dynamic-20260531T162407Z-0

Base accepted HEAD: `6cf87e9547fcbe1202d80c047d4b482e119cf36b`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-20.1`: initial `PRAGMA data_store_directory` returns an empty rowset.
- `pragma-20.2` through `pragma-20.4`: assigning a directory returns no rows and subsequent reads return the directory.
- `pragma-20.5`: relative database opens are reported by `PRAGMA database_list` under the configured data-store directory.
- `pragma-20.6`: absolute database opens bypass the configured data-store directory.
- `pragma-20.7` and `pragma-20.8`: assigning the empty string clears the directory and reads return an empty rowset again.

## Patch

- Added `SQLitePragmaDataStoreDirectory` to model the upstream `data_store_directory` PRAGMA state, quoted assignment parsing, empty-string clear behavior, and bounded `database_list` path resolution.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicDataStoreDirectory20260531Test.php`.
- Added `application-pragma-data-store-directory.php` as a generic application smoke/self-test.
- The focused corpus contributes `1001` TestRunner PASS cases and `26015` behavior assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaDataStoreDirectory.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicDataStoreDirectory20260531Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-pragma-data-store-directory.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicDataStoreDirectory20260531Test.php`
  - `1 test files, 26015 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-data-store-directory.php --self-test`
  - `application-pragma-data-store-directory self-test passed`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Status Delta

- Focused TestRunner PASS cases: `+1001`.
- Focused behavior assertions: `+26015`.
- `lane-status.json` `phpPass`: `3350074 -> 3376089`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; the upstream denominator is already fully mapped.

## Non-Overlap

This owns only upstream `pragma.test` `pragma-20.1` through `pragma-20.8` `data_store_directory` and coupled `database_list` path-resolution behavior. It avoids accepted `lock_proxy_file`, VFS file-control `pragma-19.*`, page-count, temp-store, cache-size, schema-version, table-valued PRAGMA, VFS lock/write/sync, WAL, B-tree, JSON, and SELECT clusters.

## Dependency Closure

No new support component is needed. The slice adds a bounded native PHP PRAGMA state model and reuses lane-local focused PHP TestRunner coverage plus the hydrated SQLite upstream corpus for source truth.
