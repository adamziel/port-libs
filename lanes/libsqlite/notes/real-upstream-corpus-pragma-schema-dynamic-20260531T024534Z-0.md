# real-upstream-corpus-pragma-schema-dynamic-20260531T024534Z-0

Status: focused real-upstream PRAGMA dynamic behavior growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  - `pragma2-1.*`: main-schema `PRAGMA freelist_count`
  - `pragma2-2.*`: attached-schema `PRAGMA aux.freelist_count`
  - `pragma2-3.*`: read-only `freelist_count` assignment is ignored
  - `pragma2-4.1` through `pragma2-4.8`: `cache_spill` default, OFF/ON, threshold, negative-size, and newly attached schema inheritance behavior
  - `pragma2-5.1` through `pragma2-5.3`: page-size-sensitive `cache_spill` threshold behavior

Focused PHP coverage:

- Added `SQLiteRealUpstreamPragma2CacheSpillDynamicCorpusTest.php` with 1,000 distinct TestRunner PASS cases and 6,250 focused assertions.
- The cases use generic `main`, `temp`, and `auxpragmaN` schemas and dynamic values to exercise `SQLitePragmaDynamicSchemaState` and `SQLitePragmaPagerState` without WordPress-specific fixtures or APIs.

Non-overlap:

- This does not repeat prior `pragma.test`/`pragma4.test` schema catalog rows, `pragma3.test` data-version rows, table-valued PRAGMA coverage, function/module/collation lists, schema3/schema4 DDL invalidation, or accepted pager/VFS cache-spill recovery helpers. It is limited to upstream `pragma2.test` PRAGMA state semantics for freelist and cache-spill behavior.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded PRAGMA dynamic schema state and pager PRAGMA state helpers.
