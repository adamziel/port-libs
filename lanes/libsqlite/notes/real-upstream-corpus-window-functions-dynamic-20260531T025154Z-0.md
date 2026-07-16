# real-upstream-corpus-window-functions-dynamic-20260531T025154Z-0

- Base accepted HEAD: `892244279ab2272eec684ce3477ab002d81ab0b4`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
- Ported scenarios:
  - `window5.test` `1.1`: custom `win()` sorted-state and `median()` cumulative window function behavior over the real six-row `t1(a,b)` corpus.
  - `window5.test` `2.0` and `2.1`: `sumint()` cumulative and `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING` behavior.
  - `window6.test` `1.1`, `1.2`, and `1.3`: ordered `group_concat()` and named window clause dispatch.
  - `window6.test` `10.1` and `10.2`: invalid and coercible `nth_value()` index behavior.
- Focused assertion count: `1009` assertions in `SQLiteRealUpstreamWindow5Window6DynamicTest.php`.
- Non-overlap: this avoids accepted `window4`, `window7`, `window8`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`, `windowfault`, JSON aggregate/window, row-value returning window, compound-recursive window, and runner metadata/admission clusters. It specifically owns custom-window API and named-window/nth-value behavior from `window5.test`/`window6.test`.
- Dependency closure: no new support component is needed. This reuses lane-local `SQLiteWindowFunction`, `SQLiteSelectSql`, and the focused PHP `TestRunner`; no Tcl runner, ext/sqlite, or external dependency is required.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow5Window6DynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow5Window6DynamicTest.php`: `1 test files, 1009 assertions, 0 failures`.
