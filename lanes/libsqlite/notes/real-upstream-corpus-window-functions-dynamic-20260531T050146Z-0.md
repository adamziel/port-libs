# real-upstream-corpus-window-functions-dynamic-20260531T050146Z-0

Implemented a real upstream `window1.test` dynamic regional-yield corpus in
`SQLiteRealUpstreamWindow1DynamicRegionalYield20260531Test.php`.

- Source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  sections `7.2` and `10.1-10.6`.
- Focused behavior:
  lead/lag offset defaults, regional rank/dense_rank/percent_rank/cume_dist,
  cumulative and suffix ROWS-frame sums, filtered group_concat, and ntile bucket
  distribution over generic regional application rows.
- Non-overlap:
  owns generated case numbers `0200-1219` and avoids accepted windowA/windowE,
  windowerr, windowB JSON/range, window4 navigation, window1 chained-window,
  and earlier regional-sales case ranges already present in the accepted base.
- Focused evidence:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1DynamicRegionalYield20260531Test.php`
  passed with `1 test files, 108122 assertions, 0 failures` and `1022` PASS
  lines.
- Dependency closure:
  no new support component needed; this reuses lane-local
  `SQLiteWindowFunction` ranking, offset, and ROWS-frame aggregate helpers.

Root harness was not run for this isolated micro-slice.
