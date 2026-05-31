# Real Upstream Window2 ROWS Dynamic Corpus

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T033208Z-0`
- Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
- Ported sections: `window2.test` `2.1` through `2.28`, focused on generated
  `ROWS BETWEEN` frame boundaries over `t1(a,b,c,d)`.
- Added PHP coverage:
  `lanes/libsqlite/tests/SQLiteRealUpstreamWindow2RowsDynamicCorpusTest.php`
  creates 1,200 distinct dynamic ROWS-frame TestRunner cases plus one source
  citation case.
- Focused evidence:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2RowsDynamicCorpusTest.php`
  passed with `1 test files, 9604 assertions, 0 failures` and `1201` PASS lines.
- Non-overlap: this covers `window2.test` generated ROWS frame boundary
  behavior. It does not repeat accepted `window4` value coverage,
  `window5/window6/window9` dynamic custom/ranking coverage,
  `window8` GROUPS coverage, `windowE`, or the accepted window4 value batch.
- Dependency closure: no new support component is needed; the slice reuses the
  existing native `SQLiteWindowFunction::aggregateFrameBetweenValues()` helper
  and an independent lane-local oracle for upstream ROWS frame edges.
