## real-upstream-corpus-window-functions-dynamic-20260531T035013Z-0

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`.
- Ported sections: `windowE.test` 4.1-4.2 (`total()` over `ROWS BETWEEN CURRENT ROW AND ... FOLLOWING`) and 5.1-5.2 (`sum()` over integer, huge-integer, and real tail values).
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamicWindowE20260531Test.php`.
- Focused growth: 1006 TestRunner PASS cases when run alone, with 3006 assertions. The 1000 dynamic cases vary row count, following-frame width, signed values, huge integer admission, and real-valued tail promotion around the upstream `windowE.test` frames.
- Non-overlap: this owns `windowE.test` sum/total following-frame behavior and avoids accepted `window1` chained windows, `window9` collation/filter dynamic min frames, JSON table windows, grouped SELECT text, expression ORDER BY, and storage/VFS/B-tree clusters.
- Dependency closure: no new support component needed; the slice reuses lane-local `SQLiteWindowFunction::aggregateFrameBetweenValues()` ROWS frame evaluation.
- Root harness: not run - isolated micro-slice.
