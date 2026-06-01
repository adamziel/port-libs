# real-upstream-corpus-pragma-schema-dynamic-20260601T154124Z-0

Base accepted HEAD: `4bde2909381e7674a2483c2ff6a823b5c492218d`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-2.100`: `EXPLAIN PRAGMA integrity_check` emits an
    `IntegrityCk` opcode whose operands include `1 2 8` and whose
    P4_INTARRAY root-page operand renders like `x[0-9]+,1x`.

## Behavior Ported

- Added `SQLitePragmaExplainPlan` for bounded `EXPLAIN PRAGMA integrity_check`
  and `EXPLAIN PRAGMA quick_check` opcode planning.
- Ported the upstream P4_INTARRAY rendering requirement into generic dynamic
  root-page variants, keeping the schema root page `1` as the final element of
  the rendered int-array token.
- Added guard coverage for malformed/non-explain PRAGMA input, unsupported
  PRAGMA names, empty root-page lists, and invalid root-page numbers.

## Evidence

- New focused test:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicExplainIntegrity20260601T154124ZTest.php`
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicExplainIntegrity20260601T154124ZTest.php`
  -> `1 test files, 16014 assertions, 0 failures`
- Catalog integration check:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicExplainIntegrity20260601T154124ZTest.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php`
  -> `2 test files, 16086 assertions, 0 failures`
- TestRunner PASS delta: `+1002` distinct focused PASS cases once the focused
  command is green.
- Mapped denominator delta: `0`; this deepens already mapped PRAGMA/schema
  coverage with real upstream `pragma4.test` opcode behavior.

## Non-Overlap

This slice does not repeat accepted PRAGMA result-column shape, table-valued
PRAGMA joins, table_list/corrupt-view, virtual PRAGMA rows, index_xinfo,
dynamic index reload, cache_size reload, schema-version/data-version,
temp_store, JSON, WAL, VFS, B-tree, or SELECT clusters. It owns only
`pragma4.test` `pragma4-2.100` `EXPLAIN PRAGMA integrity_check`
`IntegrityCk` P4_INTARRAY rendering.

## Dependency Closure

No new support component is needed. The slice adds a lane-local bounded PRAGMA
explain helper and reuses `SQLiteDatabase` schema/root-page information when a
database image is supplied.
