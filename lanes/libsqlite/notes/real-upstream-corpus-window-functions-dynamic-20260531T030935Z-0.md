# Real Upstream Window Functions Dynamic

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260531T030935Z-0`

Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`

This slice ports a non-overlapping dynamic subset of upstream
`/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test` into PHP
focused tests:

- `windowE.test` 4.1 and 4.2: `total()` window aggregate over
  `ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING`, including very large integer
  values coerced through floating total semantics.
- `windowE.test` 5.1 and 5.2: `sum()` window aggregate over the same following
  frame, preserving integer sums where possible and mixed integer/real output
  when a real appears in the frame.

Focused movement:

- New test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicTest.php`
- Distinct `TestRunner` PASS cases added: `1002`.
- Behavior assertions: each dynamic case checks full result vectors through
  existing `SQLiteSelectSql` parser/executor window dispatch.

Dependency closure: no new support component is needed; this reuses
lane-local `SQLiteSelectSql` and existing aggregate window execution.

Non-overlap: avoids accepted window2 following-frame boundary coverage,
window4 ntile/value behavior, window5 custom aggregate behavior, window6
ranking and frame behavior, window9 collation peer behavior, windowA/windowB
inverse RANGE and JSON object inverse behavior, windowC/windowD/window12
dynamic coverage, windowfault, mixed-type RANGE, grouped SELECT SQL text,
and all JSON/B-tree/VFS/WAL/PRAGMA/source-neutral surfaces.
