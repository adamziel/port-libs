# real-upstream-corpus-window-functions-dynamic-20260531T010257Z-0

Base accepted HEAD: `db598d2f37de4eb8809eabdfe8470ae863639e6e`.

Added `SQLiteRealUpstreamWindow2LargeDynamicTest.php` for the hydrated upstream SQLite source
`/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.

Owned upstream scenarios:

- `window2.test` section 4 table `t2`, 200 upstream rows.
- `window2.test` 4.1: `PARTITION BY (b%10) ORDER BY b`, default RANGE through current peer group.
- `window2.test` 4.3: `ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`.
- `window2.test` 4.4: `RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING`.
- `window2.test` 4.5: `RANGE BETWEEN CURRENT ROW AND CURRENT ROW`.

Focused coverage:

- 801 focused assertions / PASS lines from real upstream row-level behavior.
- Non-overlap: this uses the real 200-row section 4 corpus and avoids the existing small six-row
  `SQLiteRealUpstreamWindow2FrameBoundaryDynamicBatchTest.php`, prior `window3` generated matrix,
  and `window4` lead/lag/nth_value coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2LargeDynamicTest.php`
  - `1 test files, 801 assertions, 0 failures`

Dependency closure:

- No new support component needed. The slice reuses `SQLiteVdbeWindowAggregateCursor` and adds
  upstream-derived PHP tests only.
