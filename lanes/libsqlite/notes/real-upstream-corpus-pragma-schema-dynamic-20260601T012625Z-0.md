# real-upstream-corpus-pragma-schema-dynamic-20260601T012625Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`.
- Ported sections: `pragma-15.1`, `pragma-15.2`, and `pragma-15.3`.
- Behavior: after a peer connection changes `sqlite_schema`, schema reload must not reset this connection's assigned `PRAGMA cache_size`; only an explicit reopen resets cache size to the persistent/default value.
- Patch: `SQLitePragmaPagerState::schemaReload()` now models schema-cookie reload as distinct from `reopen()`, preserving connection-local pager PRAGMAs while returning the reload generation and dependency evidence.
- Focused growth: new test file adds 1,002 distinct TestRunner PASS cases and 15,013 focused assertions.
- Non-overlap: this does not repeat accepted PRAGMA schema live-reload catalog rows (`pragma-23.*`), malformed leaf integrity (`pragma-24.*`), data-store directory, lock-proxy/file-control, page-count, cache-spill, temp-store, schema-version, table-valued PRAGMA, VFS lock/write/sync, WAL, B-tree, JSON, or SELECT clusters. The new surface is specifically `pragma.test` `15.1..15.3` cache-size preservation across schema reload.
- Dependency closure: no new support component is needed; this reuses the lane-local pager PRAGMA state and schema-cookie reload modeling.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaPagerState.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCacheReload20260601Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCacheReload20260601Test.php` passed with `1 test files, 15013 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicCacheReload20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaPageCountDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma2CacheSpillDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `4 test files, 66022 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
