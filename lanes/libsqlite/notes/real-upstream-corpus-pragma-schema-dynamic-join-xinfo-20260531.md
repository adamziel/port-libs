# Real Upstream Corpus: PRAGMA Schema Dynamic Join/Xinfo

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T035346Z-0`

Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `6.0`: table-valued `pragma_table_list()`, `pragma_foreign_key_list()`, and `pragma_table_info()` compose across the schema argument to discover parent primary-key rows.
  - `7.1` through `7.3`: materialized `pragma_table_info()` rows and live table-valued PRAGMA rows produce the same right-join name pairs for asymmetric table definitions.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `23.2b` through `23.2e`: `PRAGMA index_xinfo` reports key and auxiliary index columns, including `DESC`, `COLLATE`, expression `cid = -2`, and auxiliary rowid `cid = -1` metadata.

## Handoff Delta

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJoinXinfo20260531Test.php`.
- The test creates 250 dynamic schema variants and 1 source-citation case.
- Verified focused movement: `1001` TestRunner PASS cases, `2503` assertions, `0` failures.
- Expected countable movement: `phpPass +1001` if the integrator accepts this focused file as non-overlapping corpus coverage.

## Non-Overlap

This does not repeat the accepted PRAGMA page-count, cache-spill, schema-version, table-list, visible/hidden JSON constraints, or previous shadowing/defaults batches. It specifically covers the upstream table-valued PRAGMA join composition and `index_xinfo` expression/descending/collation metadata rows.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` schema-catalog model.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJoinXinfo20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicJoinXinfo20260531Test.php`
- `git diff --check -- lanes/libsqlite`
