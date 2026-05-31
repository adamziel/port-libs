# real-upstream-corpus-pragma-schema-dynamic-20260531T160125Z-0

Base accepted HEAD: `b396f617ce3725e2a3fde790e5dc3841675ab023`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-16.1`: explicit `PRAGMA lock_proxy_file='mylittleproxy'` assignment returns no rows and query reads the proxy file.
- `pragma-16.2` and `pragma-16.2.1`: another connection may use the same proxy and `:auto:` reuses the known proxy.
- `pragma-16.3`: selecting from `sqlite_master` with a different proxy file reports `database is locked`.
- `pragma-16.4` through `pragma-16.9`: closed handles permit replacement, forced `:auto:` reuses or synthesizes proxy paths, and host-id mismatches keep the database locked.

## Patch

- Added `SQLitePragmaLockProxyFileState` to model bounded proxy-locking PRAGMA state over generic SQLite application database handles.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicLockProxy20260531Test.php` with 1000 dynamic upstream-backed behavior cases plus source-citation and guard cases.
- Added `lock_proxy_file` query/assignment result-shape support in `SQLitePragmaResultShape`.
- Added `application-pragma-lock-proxy-file.php` as a generic application smoke/self-test.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaLockProxyFileState.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/src/SQLitePragmaResultShape.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicLockProxy20260531Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-pragma-lock-proxy-file.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicLockProxy20260531Test.php`
  - `1 test files, 12515 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicResultShape20260531Test.php`
  - `1 test files, 4406 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-lock-proxy-file.php --self-test`
  - `application-pragma-lock-proxy-file self-test passed`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Status Delta

- Focused TestRunner PASS cases: `+1002`.
- Focused behavior assertions: `+12515`.
- `lane-status.json` `phpPass`: `3137763 -> 3150278`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; the upstream denominator is already fully mapped.

## Non-Overlap

This owns only `pragma.test` `pragma-16.1` through `pragma-16.9` `lock_proxy_file` behavior. It avoids accepted VFS process locks, lock byte ranges, lock-state application, temp-schema PRAGMA pager state, cache/page-count/schema-version/table-valued PRAGMA batches, WAL/B-tree/JSON/SELECT clusters, and the pager reader-cache `locking_proxy_file` token fence.

## Dependency Closure

No new support component is needed. The slice reuses lane-local PRAGMA result-shape handling and adds the smallest native PHP state model needed to represent SQLite proxy-locking PRAGMA behavior for focused corpus parity.
