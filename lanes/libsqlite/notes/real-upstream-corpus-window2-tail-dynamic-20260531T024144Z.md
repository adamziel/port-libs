# real-upstream-corpus-window-functions-dynamic-20260531T024144Z-0

Added a focused upstream-backed PHP corpus file for SQLite window frame behavior:

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
- Covered sections: `window2.test` 2.25-2.30 and 3.1-3.4
- PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow2TailDynamicCorpusTest.php`
- Focused growth: 1,002 local assertions / PASS lines in one focused test file

The batch exercises bounded dynamic rows across:

- unbounded full-frame `ROWS` aggregates
- partitioned unbounded frames
- `CURRENT ROW` only frames
- `RANGE CURRENT ROW AND UNBOUNDED FOLLOWING`
- text peer `RANGE` frames
- partitioned `RANGE` frames
- expression `ORDER BY d/2` running frames

Non-overlap: this intentionally avoids accepted window1/windowA/windowB/windowC/windowD/windowE,
windowerr, window4 lead/lag/ntile/nth-value, window5 custom-function, window6 following,
window7/window8 groups/range, window9 filter, window12, JSON window, and grouped SELECT text
clusters. It specifically fills the tail `window2.test` frame sections 2.25-3.4.

Dependency closure: no new support component is needed; this reuses the existing native
`SQLiteSelectSql` and `SQLiteWindowFunction` implementations with an independent frame oracle.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2TailDynamicCorpusTest.php`
  - `1 test files, 1002 assertions, 0 failures`
