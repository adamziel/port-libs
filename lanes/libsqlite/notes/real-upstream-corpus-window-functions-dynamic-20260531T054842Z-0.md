# real-upstream-corpus-window-functions-dynamic-20260531T054842Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T054842Z-0`
- Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
- Ported sections:
  - `windowE.test` `3.1`: `max(c2) OVER (ORDER BY c1 RANGE 366.0 PRECEDING)` keeps the later marker row visible to subsequent numeric RANGE frames.
  - `windowE.test` `4.1`-`4.2`: `total(b)` over `ROWS BETWEEN CURRENT ROW AND UNBOUNDED/2 FOLLOWING` preserves floating overflow and tail-frame totals.
  - `windowE.test` `5.1`-`5.2`: dynamic current-to-following ROWS totals preserve NULL skipping and floating conversion over large integer inputs.
- Extended focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php`
- Focused delta: 7008 new assertions in 1504 new TestRunner PASS cases; the full focused file now runs 28758 assertions in 2754 PASS cases.
- Non-overlap: this owns `windowE.test` numeric RANGE `max()` and ROWS `total()` overflow-tail behavior only. It avoids prior accepted `window1` lead/late corpus, `window2` frame-boundary/tail rows, `window3` ranking/distribution, `window4/window5/window6/window7/window8/window9/windowA/windowB/windowC/windowD`, JSON windows, grouped SELECT text, expression ORDER BY, VFS/WAL/B-tree, and metadata-only runner rows.
- Dependency closure: no new support component is needed; the test reuses `SQLiteWindowFunction::aggregateFrameBetweenValues()` for real upstream RANGE and ROWS aggregate semantics.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php
  No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php
  1 test files, 28758 assertions, 0 failures

git diff --check -- lanes/libsqlite
  passed
```
