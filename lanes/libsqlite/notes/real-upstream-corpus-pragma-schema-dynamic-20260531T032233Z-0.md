# real-upstream-corpus-pragma-schema-dynamic-20260531T032233Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-1.1` through `pragma-1.15`: `cache_size`, `default_cache_size`, `synchronous`, reopen reset, persistent default-cache reset, and numeric/keyword synchronous normalization.
  - `pragma-2.*`: schema-qualified synchronous behavior for attached databases.
  - `pragma-4.*`: schema-qualified cache-size/default-cache-size behavior for attached databases.
  - `pragma-5.*`: synchronous is connection-local pager state.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPragmaPagerSettingsDynamicTest.php`.
- Focused command passed with `1 test files, 32003 assertions, 0 failures`.
- The file contributes 1,001 distinct TestRunner PASS cases: 1,000 dynamic pager-setting variants plus one upstream-source citation case.

Non-overlap:

- This does not repeat accepted `pragma2.test` `cache_spill` behavior, PRAGMA schema/table/index/list metadata batches, `pragma3.test` data-version behavior, `pragma4.test` table-valued PRAGMA schema resolution, `pragma5.test` table-list/runtime catalog rows, `pragma6.test` integrity/quick-check rows, schemafault/pragmafault OOM paths, or source-neutral cleanup.
- The owned cluster is `pragma.test` pager-setting semantics for cache/default-cache/synchronous state and attached-schema independence.

Dependency closure:

- No new support component is needed. The slice reuses the existing generic `SQLitePragmaPagerState` bounded pager-setting model.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaPagerSettingsDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaPagerSettingsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
