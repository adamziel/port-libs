# Real Upstream Window3 Ranking Distribution Dynamic

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T063832Z-0`
- Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Ported sections: `1.1.3` row_number, `1.1.4` dense_rank, `1.1.5` rank, `1.1.6` combined ranking functions, and `1.1.7` percent_rank/cume_dist.
- Added focused behavior: 1,000 dynamic partition/order cases over `SQLiteWindowFunction` ranking and distribution helpers, plus fixed reduced-fixture checks and source/dependency notes.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow3RankingDistributionDynamicTest.php` -> `1 test files, 51966 assertions, 0 failures`, `1007` PASS lines.
- Expected selected movement: `+1007` PHP TestRunner PASS lines. Mapped denominator coverage remains `1589 / 1589`.
- Non-overlap: avoids accepted window1/window2 frame corpus, window7 GROUPS/RANGE, window9 collation/filter and aggregate/subquery coverage, mixed RANGE tests, chained windows, and current-source generated shard families.
- Dependency closure: no new support component needed; reuses existing native `SQLiteWindowFunction` ranking/distribution helpers.
- Root harness: not run; isolated micro-slice.
