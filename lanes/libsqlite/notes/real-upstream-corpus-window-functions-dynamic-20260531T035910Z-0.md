# real-upstream-corpus-window-functions-dynamic-20260531T035910Z-0

Base accepted HEAD: `9995fe4897b08d71e2d75db489dfa08c480a5292`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Sections `1.19.12` and `1.19.13`: generated `lead(b,b)` and `lag(b,b)` window-function cases over `t2`.

Implementation:

- Added generic `SQLiteWindowFunction::leadByRow()` and `SQLiteWindowFunction::lagByRow()` for SQLite row-dependent offset semantics.
- Added validation for row-count mismatches, non-integer offsets, and negative offsets.
- Added `SQLiteWindowDynamicRealOffsetCorpusTest.php` with 1,201 focused TestRunner cases and 40,964 assertions covering generated `t2` row slices, `lead`/`lag`, partition modes `none`, `b%10`, `b%2,a`, and order modes `a`, `b,a`, and `b%10,a`.

Non-overlap:

- Avoids accepted constant-offset lead/lag, window4/window5 value coverage, window3 aggregate EXCLUDE coverage, window8 dynamic frames, named windows, JSON object inverse/window coverage, and prior windowerr/window1 subquery-filter coverage.
- This slice exercises row-dependent offset navigation for lead/lag, which was not present in `SQLiteWindowFunction` before this patch.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteWindowDynamicRealOffsetCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicRealOffsetCorpusTest.php`
  - `1 test files, 40964 assertions, 0 failures`
  - 1,201 PASS lines

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP window-function helper and the hydrated upstream SQLite test corpus as static source truth.

Root harness:

- Not run; isolated micro-slice.
