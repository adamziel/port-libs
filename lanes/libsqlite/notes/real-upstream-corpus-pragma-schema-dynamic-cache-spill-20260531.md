## real-upstream-corpus-pragma-schema-dynamic-20260531T031739Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  - `pragma2-1.*` through `pragma2-3.*`: `schema.freelist_count` returns
    unused-page counts for main and attached schemas, and writes are ignored.
  - `pragma2-4.1` through `pragma2-4.8`: `cache_spill` defaults to cache size,
    unqualified `cache_spill=OFF` applies across schemas, schema-qualified
    threshold writes stay independent, and negative threshold syntax such as
    `cache_spill(-25)` behaves as a threshold, not OFF.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-8.1.*`: `schema_version` reads/writes remain schema-local.
  - `pragma-8.2.*`: `user_version` reads/writes do not mutate the schema
    cookie.

Implementation:

- Extended `SQLitePragmaDynamicSchemaState` with `cache_spill` state, ON/OFF
  parsing, schema-qualified cache-spill writes, global unqualified
  `cache_spill` writes, and threshold normalization against current
  `cache_size`.

Focused verification:

- Red-first before the negative-threshold fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillTest.php`
  produced `1 test files, 9005 assertions, 300 failures`; all failures were
  `cache_spill(-25)` being treated as OFF.
- After the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillTest.php`
  passed with `1 test files, 10205 assertions, 0 failures`.

PASS-line movement:

- New focused file adds 1,501 distinct TestRunner PASS cases.
- Expected lane `phpPass` movement: `1797288 -> 1798789`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLitePragmaDynamicSchemaState` support surface and the hydrated upstream
  SQLite Tcl files as source truth.

Non-overlap:

- This does not repeat accepted PRAGMA table-info/table-list shadowing,
  generated-column xinfo, PRAGMA page/application-id, schema data_version,
  prepared schema-expiry, or VFS/pager WAL PRAGMA clusters. It owns the
  distinct `pragma2.test` schema-qualified `freelist_count`/`cache_spill`
  behavior plus `pragma.test` schema/user version independence.
