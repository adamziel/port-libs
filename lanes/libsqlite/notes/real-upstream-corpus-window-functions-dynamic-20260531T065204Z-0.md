# real-upstream-corpus-window-functions-dynamic-20260531T065204Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T065204Z-0`
- Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test`

## Ported Behavior

- `windowpushd.test` `1.0-1.4`: `row_number() OVER (PARTITION BY grp_id)` view results stay partition-local when an outer `WHERE grp_id=...` predicate is pushed into the windowed source.
- `windowpushd.test` `2.1.1-2.4.3`: pushed predicates over views/subqueries with `max(...) OVER (PARTITION BY ...)` and grouped aggregate window output preserve full partition frame results before outer filtering.
- `filter1.test` `5.1-5.3`: aggregate `FILTER` over subquery rows and ordered window-prefix frames preserves the filtered count state.

## Focused Growth

- Added `SQLiteRealUpstreamWindowPushdownFilterDynamicTest.php`.
- The dynamic matrix contributes 1000 distinct TestRunner cases, each asserting pushed window row numbering, pushed source ids, and ordered filtered-count prefix state.
- Static upstream section checks and source-citation checks are included alongside the dynamic cases.

## Non-Overlap

This owns window pushdown/filter interaction semantics from `windowpushd.test` and `filter1.test`. It avoids accepted `window1` lead/outer-order cases, `window2` generated frame boundaries, `window3/window4` ranking/value offsets, `window6` nth-value/default frames, `window7/window8` GROUPS/RANGE matrices, `window9` collation/filter aggregate frames, `windowA-E` range/aggregate/inverse/collation coverage, JSON/WAL/VFS/B-tree surfaces, grouped SELECT text, expression ORDER BY, and metadata-only upstream-runner rows.

## Dependency Closure

No new support component is needed. The test reuses existing native `SQLiteWindowFunction` helpers for partition row numbering and aggregate frame `FILTER` behavior.
