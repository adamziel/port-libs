# Real Upstream Corpus: Window Dynamic Frames

Base accepted HEAD: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`.

This slice ports focused real upstream SQLite window-function behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  subtests `1.1`, `1.3`, `1.5`, `4.4`, `4.5`, `4.7`, `4.9`, and `4.10.2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  subtests `1.1`, `2.1`, `2.3`, `2.4`, `2.6`, `2.8`, `2.11`, `2.14`,
  `2.16`, `2.17`, `2.19`, `2.20`, `2.23`, `2.24`, `2.29`, `2.30`, and
  `3.4`.

Red-first result:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicFramesTest.php`
  initially failed on expression-wrapped window functions, custom
  `group_concat()` window separators, preceding-only and following-only ROWS
  frames, unbounded-following INF casts, and text RANGE peer frames.

Implemented behavior:

- `SQLiteSelectSql` now lets scalar expression parsing handle nested window
  functions instead of treating any `OVER` token as a top-level window call.
- `SQLiteSelectSql` preserves original frame start/end boundary text so the
  executor can distinguish `1 PRECEDING AND 1 PRECEDING`,
  `1 FOLLOWING AND 3 FOLLOWING`, and `CURRENT ROW AND UNBOUNDED FOLLOWING`.
- `SQLiteSelectQuery` materializes nested window expressions before projection
  and passes boundary-preserving frame requests to `SQLiteWindowFunction`.
- `SQLiteWindowFunction` evaluates boundary-preserving aggregate frames,
  handles text RANGE peers for upstream `CURRENT ROW`/`UNBOUNDED FOLLOWING`
  cases, and honors `group_concat(value, separator)` in window frames.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicFramesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicFramesTest.php`
  passed: `1 test files, 26 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

No new support component is needed. This reuses the existing generic
`SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction` machinery.
