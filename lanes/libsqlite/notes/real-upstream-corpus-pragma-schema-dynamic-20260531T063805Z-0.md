# Real Upstream PRAGMA Schema Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-pragma-20260531T063805Z`
Base accepted HEAD: `e80280ab3ef4a3dc0e83a28a18647e19ca0381e1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Ported sections:
  - `pragma-8.3.1`: `PRAGMA application_id` reads the header application id, defaulting to `0`.
  - `pragma-8.3.2`: mixed-case `PRAGMA Application_ID(12345)` assigns the value and `PRAGMA application_id` reads it back.

## Local Movement

- Added `SQLiteRealUpstreamPragmaApplicationIdDynamicTest.php` with 1000 generated behavior cases plus 2 citation/countability cases.
- Focused result: `1 test files, 14004 assertions, 0 failures`, with 1002 PASS lines.
- Expected `phpPass` movement: `2564612 -> 2565614` if accepted as a non-overlapping focused corpus file.

## Non-Overlap

This slice covers upstream `pragma.test` section `8.3` application-id pragmas. It does not repeat the accepted PRAGMA temp-store, cache-spill, schema-version/user-version/data-version, schema-join, shadowing, fault/integrity, namespace-default, table-valued, or `pragma6` generated-schema integrity batches.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP test harness and the existing generic `SQLitePragmaEncodingPageTempStoreState` PRAGMA state helper.
