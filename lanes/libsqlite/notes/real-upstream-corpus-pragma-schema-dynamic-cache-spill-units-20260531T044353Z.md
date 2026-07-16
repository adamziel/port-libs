# real-upstream-corpus-pragma-schema-dynamic-20260531T044353Z-0

Implemented a real upstream PRAGMA/schema dynamic batch from hydrated SQLite
upstream `test/pragma2.test`.

## Upstream Sections

- `pragma2-4.5.2`: explicit `PRAGMA cache_spill=N` threshold state.
- `pragma2-4.5.4`: parenthesized negative `PRAGMA cache_spill(-N)` threshold.
- `pragma2-4.6`: schema-qualified cache-spill behavior on attached schemas.
- `pragma2-4.8`: unqualified cache-spill changes broadcast to attached schemas.
- `pragma2-5.1` through `pragma2-5.3`: negative cache-spill byte thresholds are converted using the active page size.

## Focused Coverage

- Added `SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillUnitsTest.php`.
- Adds 1,002 TestRunner PASS cases and 5,757 focused assertions.
- The 1,000 generated behavior cases vary page size, cache size, negative byte thresholds, attached schema names, schema-qualified isolation, and unqualified broadcast behavior.

## Non-Overlap

This does not repeat the existing `SQLiteRealUpstreamPragma2CacheSpillDynamicCorpusTest.php` sample of `PRAGMA cache_spill(-51)` at page size 16384. The new batch expands the same upstream `pragma2` section across all SQLite page-size units and explicitly asserts schema-qualified isolation versus unqualified broadcast semantics.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillUnitsTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillUnitsTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillUnitsTest.php`
  - `1 test files, 5757 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the existing `SQLitePragmaPagerState` parser and pager PRAGMA state model.
