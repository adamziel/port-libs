# Real Upstream Window1 Chained Dynamic Slice

- Session: `port-dev-sqlite-yield-dyn-real-window-20260531T033706Z`
- Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260531T033706Z-0`
- Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `18.3.1-18.3.5`

## Behavior

Added `SQLiteRealUpstreamWindow1ChainedDynamicTest.php` for chained window-definition behavior from `window1.test`.
The focused cluster preserves upstream semantics for inherited partition definitions with later `ORDER BY c` resolution and ordered `group_concat`/`string_agg`-style output across direct, inline inherited, named inherited, parenthesized named, and deep chained windows.

The dynamic matrix mutates row order, partition labels, value labels, and separators while preserving the same upstream invariant: the inherited partition is evaluated first, the ordered chain is cumulative inside each partition, and each partition emits a stable final chain length.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1ChainedDynamicTest.php`
  - Result: `1 test files, 6863 assertions, 0 failures`
  - PASS lines: `1006`

## Non-Overlap

This slice avoids the accepted `window1` basic aggregate, view/trigger, sales lead, `window2`, `window3`, `window4`, `windowE`, pushdown, range/null, and windowerr clusters. It targets `window1.test` chained window definitions (`18.3.1-18.3.5`) as a distinct upstream behavior cluster.

## Dependency Closure

No new support component is needed. The test reuses lane-local `SQLiteWindowFunction` aggregate frame behavior and PHP test runner infrastructure only.
