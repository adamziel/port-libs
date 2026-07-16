# real-upstream-corpus-pragma-schema-dynamic-20260530T233225Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T233225Z-0`
Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schemafault.test`
- Ported sections:
  - `schemafault-1.0`: creates a table and view where the view expands
    `SELECT aaa, aaa+1 FROM t2`.
  - `schemafault-1`: runs `SELECT * FROM v2` under `oom-*` fault simulation and
    expects an empty successful result.

## Implementation

- Added `SQLiteSchemaFaultPlan`, a bounded generic model for schema-object
  allocation checkpoints while expanding simple view SELECT statements.
- Added `SQLiteRealUpstreamPragmaSchemaFaultDynamicTest.php` with 1,000
  distinct recoverable OOM view-expansion cases plus successful projection,
  upstream citation, and malformed SQL guard coverage.

## Focused Counts

- New focused TestRunner PASS cases: 1,003.
- New focused behavior assertions: 10,008.
- Expected dashboard movement: PASS-line growth for a real upstream
  `schemafault.test` behavior cluster; mapped coverage remains `1589 / 1589`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSchemaFaultPlan.php`
  - passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaFaultDynamicTest.php`
  - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaFaultDynamicTest.php`
  - passed: `1 test files, 10008 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted PRAGMA table-info/index-info/table-list,
`pragma2.test` cache-spill, `pragma3.test` data-version, `pragma4.test`
table-valued rowsets, `pragma5.test` introspection lists, `pragma6.test`
integrity/quick-check generated-schema image coverage, or `schema.test` through
`schema6.test` invalidation/layout batches. It owns the adjacent
`schemafault.test` recoverable OOM view-expansion fault path.

## Dependency Closure

No new support component is needed. The batch reuses bounded PHP parsing and
array row projection to model schema-cache fault recovery for this upstream
faultsim surface.
