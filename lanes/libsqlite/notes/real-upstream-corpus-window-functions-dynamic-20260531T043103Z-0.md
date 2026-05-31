# real-upstream-corpus-window-functions-dynamic-20260531T043103Z-0

Base accepted HEAD: `9c639ff85ec75b07f4dd143b6bbb0e832cdb6a85`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
- Ported sections: `windowpushd.test` `1.0-1.4` and `2.0-2.3.6`.

Behavior added:

- Added `SQLiteRealUpstreamWindowPushdownDynamic20260531Test.php`.
- The test ports the upstream window pushdown invariant: outer predicates on a windowed view/subquery must not change window partition results computed by `row_number()` and partition aggregate windows.
- The dynamic corpus creates 1,000 generic setting-row variants with varying partition counts, row counts, metrics, running sums, and outer predicates, then compares lane-local window-function output against an independent oracle that annotates full partitions before applying the predicate.
- Focused count: 1,002 TestRunner PASS cases and 38,570 assertions.

Non-overlap:

- This owns `windowpushd.test` partition-preserving predicate pushdown behavior.
- It does not repeat accepted `window1`/`window2` frame cases, `window3`/`window4` ranking/value cases, `window6` value/default-frame cases, `window7` groups/range frames, `windowC` separator cases, `windowD` truth cases, `windowE` sum/total overflow cases, `windowerr`, `windowfault`, JSON window behavior, SQL expression ORDER BY, JSON table source/cursor/constraint work, or any metadata-only runner rows.
- It adds no WordPress-specific libsqlite APIs or source names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamic20260531Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamic20260531Test.php` passed: `1 test files, 38570 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteWindowFunction` row-number and aggregate frame evaluation to model the upstream window-pushdown preservation contract.
