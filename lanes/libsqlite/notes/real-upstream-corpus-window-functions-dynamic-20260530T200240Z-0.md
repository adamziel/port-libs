# Real Upstream Window Functions Dynamic Batch

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T200240Z-0`
- Accepted base: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
- Ported scenario names:
  - `window7.test` `1.2`: `GROUPS BETWEEN CURRENT ROW AND CURRENT ROW`
  - `window7.test` `1.3`: `GROUPS BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `window7.test` `1.4`: `GROUPS BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `window7.test` `1.5`: `RANGE BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `window7.test` `1.6`: `RANGE BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `window7.test` `1.7`: `RANGE BETWEEN 2 PRECEDING AND 1 FOLLOWING`
  - `window7.test` `1.8.1`: ascending `RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING`
  - `window7.test` `1.8.2`: descending `RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING`
- Focused coverage: `1600` distinct TestRunner PASS cases and `3200` behavior assertions over the real upstream 100-row `t3(a,b)` corpus.
- Changed behavior surface: no production source change was required; this exercises existing native `SQLiteWindowFunction::aggregateFrameBetweenValues()` and `aggregateOrderedRangeValues()` against independent in-test GROUPS/RANGE frame oracles.
- Non-overlap: this targets upstream `window7.test` RANGE/GROUPS sum frames only. It does not repeat accepted `window3`, `window4`, `window5`, `window6`, `window8`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowfault`, `windowpushd`, JSON window, row-value/window, compound/window, WAL, VFS, B-tree, PRAGMA, trigger, or suite-evidence surfaces.
- Dependency closure: no new support component is needed; this reuses lane-local native window aggregate helpers.
