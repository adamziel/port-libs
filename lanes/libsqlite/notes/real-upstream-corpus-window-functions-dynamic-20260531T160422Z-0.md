# real-upstream-corpus-window-functions-dynamic-20260531T160422Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T160422Z-0`

Base accepted HEAD: `babccb1e8657d71e59b3c627c9000c66f8705d7f`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `window1.test` `23.1` through `23.6`.

Behavior:

- Added `SQLiteWindowSortReusePlan` to model the planner order-count behavior
  exercised by upstream `do_ordercount_test`.
- `PARTITION BY` terms are folded before `ORDER BY` terms for window sorter
  signatures, so `ORDER BY a,b` and `PARTITION BY a ORDER BY b` share the same
  `(a,b)` order.
- Existing index prefixes satisfy compatible window sort keys without a temp
  order, while identical unsatisfied sort signatures share one temp order.
- Frame unit differences (`ROWS`, `RANGE`, `GROUPS`) do not force extra temp
  orders when the partition/order key is identical, matching `window1.test`
  `23.4` and `23.5`.

Focused test growth:

- Added `SQLiteRealUpstreamWindow1PlannerSortDynamicTest.php`.
- Red-first before implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1PlannerSortDynamicTest.php`
  failed with missing `PortLibs\LibSqlite\SQLiteWindowSortReusePlan`:
  `1 test files, 2 assertions, 1006 failures`.
- After implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1PlannerSortDynamicTest.php`
  passed with `1 test files, 5026 assertions, 0 failures`.

Non-overlap:

- This owns only upstream `window1.test` section 23 planner sorter reuse/order
  count behavior.
- It does not repeat accepted `window1` regional sales, subquery partitions,
  chained windows, range offsets, aggregate rows, alias `ORDER BY`, `window2`
  frame/exclude corpora, `window3` ranking, `window4` navigation/value
  functions, `window5` custom aggregates, `window6` keyword/default-frame
  behavior, `window7`/`window8` GROUPS/RANGE frames, `window9` filter/collation,
  `windowA` through `windowE`, pushdown, filter, JSON, WAL, VFS, B-tree, or
  metadata-only runner rows.

Expected dashboard movement:

- Selected libsqlite evidence moves from `3194686` to `3199712 pass / 0 fail`
  from the focused `+5026` behavior assertions.
- Mapped coverage remains `1589 / 1589`; the upstream denominator is already
  fully mapped.

Dependency closure:

- No new support component is needed. This reuses lane-local native PHP planner
  metadata and window sort-key normalization; no ext/sqlite, Tcl runner, or
  external service is required.
