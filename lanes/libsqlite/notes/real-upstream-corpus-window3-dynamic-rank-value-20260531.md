# Real Upstream Window3 Dynamic Rank/Value Corpus

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T031824Z-0`

Base accepted HEAD: `148cfd0e2c7cc75dba20ff0e424e615192f1e7c6`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Ported sections: `3.1-3.6` row_number/rank/dense_rank, `4.1-4.6` percent_rank/cume_dist, `5.1-5.6` ntile, and `6.1-9.6` first_value/last_value/nth_value/lead/lag.

Handoff content:

- Added `SQLiteRealUpstreamWindow3DynamicRankValue20260531Test.php`.
- Adds 1,000 dynamic upstream-derived behavior cases plus source-citation and dependency-closure tests.
- Focused result: `1 test files, 53198 assertions, 0 failures`.
- Expected TestRunner PASS-line movement: `+1002`.

Non-overlap:

- Does not repeat accepted `window1/window2` dynamic frame coverage, `window7` GROUPS/RANGE frame coverage, `windowD` truth/predicate coverage, JSON table window ranking, grouped SELECT text, or row-value returning window continuation slices.
- This slice targets `window3.test` ranking/distribution/bucket/value/offset function families over varied partitions and order shapes.

Dependency closure:

- No new support component needed.
- Reuses native `SQLiteWindowFunction` ranking, distribution, bucket, value, and offset helpers.
