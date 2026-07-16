# Real Upstream Window Functions Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T185829Z-0`
- Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`

## Ported Upstream Scenarios

- `window5.test:1.1` custom `win()` sorted window state and `median()` window value behavior over `ORDER BY b`.
- `window5.test:2.0` custom `sumint()` running window over `ORDER BY rowid`.
- `window5.test:2.1` custom `sumint()` over `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING`.
- Dynamic expansion of the same upstream custom-window-function frame family over `ROWS` and `GROUPS` start/end boundary grids, including empty reversed frames and full unbounded frames.

## Implementation

- Added `SQLiteWindowFunction::customFrameStateValues()` for generic custom numeric window states:
  - `median`
  - `sorted_values`
  - `sumint`
- The helper uses existing lane-local frame-boundary handling and does not add domain-specific APIs or metadata-only runner rows.

## Focused Evidence

- New focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomFunctionDynamicTest.php`
- Focused TestRunner result:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomFunctionDynamicTest.php`
  - Result: `1 test files, 6045 assertions, 0 failures`
- PASS-line growth: `1221` distinct focused TestRunner PASS cases.
- Behavior assertion growth: `6045` assertions.

## Non-Overlap

This slice does not repeat accepted `window2`, `window3`, `window4`, `window6`, `windowA`, window NULL placement, frame-boundary, JSON window, row-value/window, compound/window, WAL, VFS, B-tree, PRAGMA, trigger, or suite-evidence work. It targets upstream `window5.test` custom window function state and median/sumint behavior only.

## Dependency Closure

No new support component is needed. The change reuses existing native PHP window frame machinery in `SQLiteWindowFunction` and adds a bounded generic custom-window-state helper.
