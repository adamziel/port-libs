# real-upstream-corpus-pragma-schema-dynamic-20260531T041719Z-0

This slice ports a non-overlapping real upstream PRAGMA temp-store transaction
cluster from the hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-9.11` through `pragma-9.14`: numeric `temp_store` mode assignment
    remains mutable outside active temp-table work.
  - `pragma-9.15`: changing `PRAGMA temp_store` while a temp table transaction
    is active is rejected with `temporary storage cannot be changed from within
    a transaction`.
  - `pragma-9.16`: committed temp-table rows remain readable after the rejected
    temp-store change.
  - `pragma-9.18`: changing `PRAGMA temp_store` during an active temp-table scan
    is rejected with the same transaction error.

Implementation movement:

- `SQLitePragmaEncodingPageTempStoreState` now models bounded generic temp-table
  transaction and scan state, preserves temp rows through commit, and rejects
  `temp_store` assignments while a transaction or scan is active.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicTempStoreTransactionTest.php` with
  1,000 dynamic real-upstream behavior PASS cases plus one source-citation case.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePragmaEncodingPageTempStoreState.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreTransactionTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreTransactionTest.php`
  - `1 test files, 4753 assertions, 0 failures`
  - 1,001 focused PASS lines

Non-overlap:

This does not repeat the accepted PRAGMA schema/table-valued batches,
application-id/schema-version/user-version state, cache-spill/data-version,
journal/synchronous state, page-count, object-name collision, or earlier
encoding/page/temp-store static corpus. The owned behavior is the upstream
`pragma.test` temp-store transaction/scan rejection and committed temp-row
preservation cluster.

Dependency closure:

No new support component is needed. This reuses and extends the existing
lane-local PRAGMA encoding/page/temp-store state model.
