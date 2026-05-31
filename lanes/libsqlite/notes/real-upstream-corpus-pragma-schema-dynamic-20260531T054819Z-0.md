# real-upstream-corpus-pragma-schema-dynamic-20260531T054819Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T054819Z-0`

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported sections: `pragma-9.1` through `pragma-9.18`

## Behavior

- `PRAGMA temp_store` query and assignment for `DEFAULT`, `FILE`, `MEMORY`, and numeric values `0`, `1`, `2`, and `3`.
- Upstream-compatible `temp_store=3` normalization back to default (`0`).
- Rejection of `temp_store` changes while a temp transaction or active temp-table scan is in progress.
- Committed temp rows remain readable after the transaction is committed.

## Non-Overlap

This does not repeat accepted PRAGMA schema table-info/index/list/table-valued batches, `schema_version`/`user_version`, data-version, cache-spill, application-id, page-count, schema invalidation, or the previously rejected PRAGMA expected-shape handoff. It targets only upstream `pragma.test` temp-store state and transaction/scan rejection behavior in the existing generic `SQLitePragmaEncodingPageTempStoreState` model.

## Dependency Closure

No new support component is needed. This reuses the existing bounded PRAGMA state model and temp-table transaction/scan helpers.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaEncodingPageTempStoreState.php` - pass
- `php -l lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php` - pass
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreCorpusTest.php` - pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreCorpusTest.php` - `1 test files, 20004 assertions, 0 failures` with `1001` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreCorpusTest.php` - `2 test files, 20073 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` - pass

Root harness: not run - isolated micro-slice.
