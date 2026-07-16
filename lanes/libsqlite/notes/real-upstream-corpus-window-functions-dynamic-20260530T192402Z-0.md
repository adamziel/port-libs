# Real Upstream Window Functions Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T192402Z-0`
- Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test`

## Ported Upstream Scenarios

- `windowC.test` `1.text.1` through `1.text.5`: varying text separators for
  `group_concat('val', x) OVER (...)`.
- `windowC.test` `1.blob.1` through `1.blob.5`: BLOB separator variants for
  the same window frame family.
- `windowC.test` `1.*.2.1` through `1.*.2.3`: `ROWS BETWEEN 1 PRECEDING AND 1
  FOLLOWING`, `ROWS BETWEEN 2 PRECEDING AND CURRENT ROW`, and `ROWS BETWEEN
  CURRENT ROW AND UNBOUNDED FOLLOWING`.
- `windowC.test` `2.0` and `2.1`: UTF-16le/UTF-16be fuzz-derived separator
  cases with integer and BLOB values over `ROWS BETWEEN 1 PRECEDING AND 1
  PRECEDING`.

## Implementation

- Added `SQLiteWindowFunction::groupConcatFrameBetweenSeparators()` for generic
  per-row `group_concat(value, separator)` window frames.
- The helper reuses existing native frame-boundary, `EXCLUDE`, and SQL truthy
  filter behavior; it does not add metadata-only runner rows or domain-specific
  APIs.

## Focused Evidence

- New focused test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindowCGroupConcatDynamicTest.php`
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowCGroupConcatDynamicTest.php`
  passed with `1 test files, 58435 assertions, 0 failures`.
- Focused PASS cases added: `33540` distinct TestRunner cases.
- Behavior assertion growth: `58435` assertions.

## Non-Overlap

This slice targets upstream `windowC.test` variable separator handling only. It
does not repeat accepted `window3`, `window4`, `window5`, `window8`,
`windowA`, `windowB`, `windowE`, `windowfault`, `windowpushd`, JSON window,
row-value/window, compound/window, WAL, VFS, B-tree, PRAGMA, trigger, or
suite-evidence work.

## Dependency Closure

No new support component is needed. The change reuses lane-local
`SQLiteWindowFunction` frame machinery and `SQLiteBlobValue` value-text
handling.
