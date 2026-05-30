2026-05-30 real upstream window-functions dynamic corpus

- Slice: real-upstream-corpus-window-functions-dynamic-20260530T212631Z-0
- Accepted base: 551608c47b9b5c9b4c74afdd6349b99f03720fcd
- Added focused TestRunner file: lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicCorpusTest.php
- Upstream source truth:
  - /home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test sections 1.1-5.4
  - /home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test sections 2.20-4.3
- Coverage shape: 1,200 distinct dynamic ROWS/RANGE aggregate-window cases plus one source-citation case.
- Behavior assertions: 6,001 focused assertions.
- PASS-line growth: 1,201 focused TestRunner PASS cases.
- Non-overlap: extends the real upstream window corpus with dynamic aggregate frame combinations across sum/count/total/avg/min/max, FILTER, EXCLUDE, ROWS, and RANGE. It does not modify or repeat accepted windowA/windowB/windowC/windowD/windowE/window9 files, JSON table windows, grouped SELECT text, expression ORDER BY, or root-gate window no-order guard coverage.
- Dependency closure: no new support component needed; this reuses the lane-local SQLiteWindowFunction aggregate frame implementation and independent test-local expected frame calculation.
- Root harness: not run - isolated micro-slice.
