# Real Upstream Corpus Window4 Dynamic Slice

- Session: `port-dev-sqlite-yield-dyn-real-window-20260531T041759Z`
- Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Ported sections: `window4.test` `1.1` through `1.19` (`ntile()` buckets), `2.1` (`nth_value(b,c)` with row-dependent indexes), `2.2.1` through `2.3.3` (`lead()`/`lag()` offsets and defaults), `2.4.1` (`group_concat()` over following rows), and `3.5.1` through `3.6.3` (empty and one-row ROWS frame boundaries).
- Added PHP behavior file: `lanes/libsqlite/tests/SQLiteWindow4DynamicRealCorpusTest.php`
- Focused movement: `1,010` distinct TestRunner PASS cases, `13,659` behavior assertions.
- Non-overlap: this does not repeat the latest accepted `windowE` custom collation or `window3` real-offset row-dependent `lead()`/`lag()` corpus; it ports `window4.test` bucket distribution, row-dependent `nth_value()`, static offset defaults, following-frame concatenation, and empty single-row max frame boundaries.
- Dependency closure: no new support component is needed; the existing `SQLiteWindowFunction` frame, ranking, value, offset, and aggregate primitives are reused.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindow4DynamicRealCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 13659 assertions, 0 failures
```

