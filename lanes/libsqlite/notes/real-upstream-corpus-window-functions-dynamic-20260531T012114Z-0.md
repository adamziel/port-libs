# real-upstream-corpus-window-functions-dynamic-20260531T012114Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T012114Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`

Covered upstream scenarios:

- `windowpushd.test` `1.0-1.4`: view-level `row_number() OVER (PARTITION BY grp_id)` with an outer `grp_id` predicate. The PHP corpus verifies the row numbers are assigned over the partitioned view rows and remain stable after the outer filter.
- `windowpushd.test` `2.0-2.4.3`: subquery/view window behavior with `max() OVER (PARTITION BY ...)`, `row_number() OVER (...)`, grouped rows, and outer predicates. The PHP corpus verifies filters are applied after the window result rows are formed, not by recomputing the window over the filtered rows.
- Dynamic cases expand the same upstream fixtures across equality/range predicates, non-partition predicates, and grouped-subquery filters to cover pushdown-style row preservation without using generated fake upstream script names.

Implementation delta:

- Added `SQLiteRealUpstreamWindowPushdownDynamicTest.php`.
- No production source changes were required. The test reuses native PHP `SQLiteWindowFunction` row-number and aggregate-frame helpers plus in-test row-array oracles for the `windowpushd.test` fixtures.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`
  - `1 test files, 36684 assertions, 0 failures`
  - `3009` focused PASS lines.

Non-overlap:

- This batch does not repeat accepted `window1`, `window2`, `window3`, `window4`, `window6`, `window7`, `window9`, `windowC`, `windowD`, or `windowE` frame/value/collation/truth-function batches. It specifically owns `windowpushd.test` pushdown-preservation behavior over windowed views and grouped subqueries.
- No metadata-only admission records, generated fake `.test` identifiers, app-specific API names, or domain-shaped examples were added.

Dependency closure:

- No new support component is needed. This reuses lane-local native window helpers and focused TestRunner infrastructure; no ext/sqlite, Tcl runner, or new dependency activation is required.
