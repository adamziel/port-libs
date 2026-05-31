# real-upstream-corpus-window-functions-dynamic-20260531T001237Z-0

Base accepted HEAD: `a90bd8ebc7d2ac86175490c2392e0f42be214ce6`.

Added `SQLiteRealUpstreamWindowDBooleanViewDynamicTest.php`, porting real upstream SQLite `test/windowD.test` sections:

- `windowD.test 1.0-1.4`: `cume_dist()` in a view, TRUE projection, `IS`, and `IS FALSE` predicates.
- `windowD.test 2.0-2.6`: projected TRUE/FALSE constants and a window aggregate view column compared with `IS`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDBooleanViewDynamicTest.php`
- Result: `1 test files, 8406 assertions, 0 failures`
- PASS-line growth: `1207` distinct focused TestRunner PASS cases.

Non-overlap:

- Does not repeat accepted `window1`, `window2`, `window4` value/offset/frame, `window5`, `window8`, `window9`, `windowB`, `windowC`, `windowE`, or `windowpushd` coverage.
- This slice owns the previously distinct `windowD.test` view materialization and boolean `IS` predicate behavior over window-derived rows.

Dependency closure:

- No new support component is needed. The batch reuses the existing `SQLiteWindowFunction::cumeDist()` helper and lane-local SQLite scalar comparison modeling.
