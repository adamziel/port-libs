# real-upstream-corpus-window-functions-dynamic-20260531T032246Z-0

Base accepted HEAD: `582d5b219b619868bb38159464dc8e8768230ba8`.

Implemented one lane-local real upstream window corpus slice:

- Added `lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusExcludeTest.php`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`.
- Ported scenario family: generated `window3.test` sections `1.18` and `1.20`, especially `ROWS BETWEEN 4 PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW` and `EXCLUDE GROUP`.
- The PHP corpus keeps the real upstream `t2(a,b)` rows and expands the same EXCLUDE-frame behavior across bounded slices, order-key modes (`a`, `b`, `b%10`, `b%2/b%10`), aggregate functions, and value functions.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusExcludeTest.php`
- Result: `1 test files, 12000 assertions, 0 failures`
- PASS lines: `1000`

Expected dashboard movement if accepted:

- `phpPass`: `1834768 -> 1835768` (`+1000`)
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`
- Count as PASS-line growth only.

Non-overlap:

- Does not repeat accepted `windowE` frame behavior, `window8` dynamic frame matrix, `window5` custom inverse rows, `window6` rank/recursive rows, `window9` collation ranking, `windowC/windowD/window12`, JSON-object inverse, pushdown, group-concat, boolean-view, windowfault, or mixed-type REAL coverage.
- No new production APIs, no generated fake upstream script ids, and no metadata-only PASS rows.
- No new domain-specific source text.

Dependency closure:

- No new support component is needed. The slice reuses existing `SQLiteWindowFunction` frame aggregation and value-frame helpers.
