# real-upstream-corpus-window-functions-dynamic-20260531T042256Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test`

Ported sections:

- `window9.test` `7.1` through `7.4`: cumulative `avg(x) OVER (ORDER BY y)`
  values sorted by the window alias, boolean `IS` order expressions, and
  numeric alias expressions.
- `window9.test` `8.1.1` through `8.4`: aggregate inputs feeding a window
  aggregate, grouped aggregate rows feeding a window aggregate, and scalar
  subquery/compound output preserving the numeric window aggregate result.
- `window9.test` `9.1`: negative text RANGE ending offset rejection.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php`.
- Focused run: `1 test files / 12609 assertions / 0 failures / 1209 PASS lines`.
- Expected selected `phpPass` movement: `+1209`, from `2025275` to `2026484`.
- Mapped coverage remains `1589 / 1589`; this is accepted-denominator behavior
  growth from a hydrated upstream window file, not a new inventory row.

Non-overlap:

This batch avoids accepted `windowpushd`, `window5`, `window9` nocase/filter
frames, `windowA` through `windowE`, `windowerr`, `windowfault`, JSON window,
row-value/window, compound/window, WAL, VFS, B-tree, PRAGMA, trigger, and suite
metadata clusters. It owns the `window9.test` aggregate-window/subquery/order
expression tail around sections `7`, `8`, and `9.1`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php` -> `1 test files, 12609 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php` -> no syntax errors.
- `git diff --check -- lanes/libsqlite` -> passed.

Dependency closure:

No new support component is needed. This reuses the lane-local
`SQLiteWindowFunction` aggregate frame helpers and focused TestRunner
infrastructure; no ext/sqlite, Tcl runner, or new dependency activation is
required.
