# real-upstream-corpus-window-functions-dynamic-20260531T045349Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
- Ported scenarios:
  - `window8.test` `7.1.1`: `sum(a)` over `ORDER BY b NULLS LAST RANGE BETWEEN 6 FOLLOWING AND UNBOUNDED FOLLOWING`
  - `window8.test` `7.2.1`: `min(a)` over the same NULLS LAST RANGE frame
  - `window8.test` `7.4.1`: `max(a)` over the same NULLS LAST RANGE frame

Behavior:

- `SQLiteVdbeWindowAggregateCursor` now treats `NULL` ORDER BY candidates as the far end of the numeric RANGE domain according to explicit/default `NULLS FIRST` or `NULLS LAST` placement when the current row has a numeric RANGE value.
- Non-numeric current rows still use peer-only RANGE behavior.
- Added `SQLiteRealUpstreamWindow8NullRangeDynamicTest.php` with 19 focused assertions over the real upstream six-row `t2(a,b)` corpus.

Non-overlap:

- This does not repeat the accepted `window7` group/range matrix, `window8` generated GROUPS corpus, window pushdown, window ranking/distribution, JSON object inverse, window C/D/E, or window fault/error coverage. It owns the mixed numeric/NULL RANGE boundary behavior from `window8.test` section 7.1.1/7.2.1/7.4.1 only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow8NullRangeDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8NullRangeDynamicTest.php`: `1 test files, 19 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupRangeDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8DynamicGroupsCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8NullRangeDynamicTest.php`: `4 test files, 73382 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the native PHP window aggregate cursor and focused TestRunner infrastructure; no ext/sqlite, Tcl runner, or external dependency activation is required.

Follow-up:

- The broader `window8.test` section 7 inverted/far PRECEDING and FOLLOWING frames still need a dedicated RANGE-boundary slice. This patch intentionally does not claim those upstream rows.
