# real-upstream-corpus-pragma-schema-dynamic-20260601T023640Z-0

Base accepted HEAD: `d66a5b3de6df2dc65a32a2f70e37d0a3eee8d74f`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test`
  - `1.100` through `1.160`: generated columns may use innocuous functions
    when `trusted_schema` is off, while non-innocuous functions are blocked in
    persistent schema text and direct-only functions are only allowed in TEMP.
  - `1.200` through `1.320`: CHECK and DEFAULT expressions apply the same
    safety gate, and explicit DEFAULT-column values bypass unsafe defaults.
  - `1.400` through `1.540`: partial and expression indexes reject unsafe
    persistent schema functions under `trusted_schema=OFF`, while TEMP schema
    index expressions are allowed.
  - `2.100` through `3.131`: views and triggers defer direct-only function
    rejection to runtime and reject non-innocuous functions when
    `trusted_schema=OFF`.
  - `4.1` through `4.2`: innocuous built-ins such as `json_extract()` remain
    allowed with `trusted_schema` off or on.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-1.*`: `PRAGMA trusted_schema` query/assignment result-shape
    semantics match the boolean PRAGMA family.

## Behavior Ported

- Added `SQLitePragmaTrustedSchemaPlan` to model the bounded upstream safety
  gate for schema-embedded function calls.
- Added real upstream dynamic coverage for persistent vs TEMP schema function
  use across generated columns, CHECK constraints, DEFAULT expressions,
  partial indexes, expression indexes, views, triggers, direct SQL calls, and
  innocuous built-in functions.
- Reused existing PRAGMA boolean state/result-shape helpers for
  `PRAGMA trusted_schema` query and assignment behavior.

## Evidence

- New focused test:
  `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTrustedDynamicTest.php`
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTrustedDynamicTest.php`
  -> `1 test files, 15068 assertions, 0 failures`
- TestRunner PASS delta: `+1007` distinct focused PASS cases.
- Mapped denominator delta: `0`; this deepens already mapped PRAGMA/schema
  coverage with real upstream `trustschema1.test` behavior.

## Non-Overlap

This slice does not repeat the accepted pragma shadowing, table-valued PRAGMA
join, corrupt-view table_list, temp_store, data_store_directory,
count_changes, page_count/application_id, collation-list virtual table, JSON
table, WAL, VFS, B-tree, or SELECT clusters. It owns only
`trustschema1.test` `PRAGMA trusted_schema` schema-function safety behavior.

## Dependency Closure

No new support component is needed. The slice reuses lane-local PRAGMA boolean
state/result-shape helpers and adds a bounded native PHP trusted-schema safety
model.
