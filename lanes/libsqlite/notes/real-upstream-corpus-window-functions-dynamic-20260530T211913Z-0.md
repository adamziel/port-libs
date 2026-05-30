# real-upstream-corpus-window-functions-dynamic-20260530T211913Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test`.
- Ported upstream sections:
  - `windowA.test:1.1-1.6` for `ORDER BY d DESC NULLS FIRST/LAST RANGE` finite preceding/following and current-row frames.
  - `windowA.test:2.1-2.6` for unbounded preceding combinations with `NULLS FIRST/LAST`.
  - `windowA.test:3.1-3.4` for current-row through following/unbounded following combinations.
  - `windowA.test:4.0` for a sparse preceding-only frame that returns empty string/NULL-equivalent aggregate results for non-overlapping numeric ranges.
- Focused coverage: `SQLiteRealUpstreamWindowARangeNullsDynamicTest.php` adds 1007 distinct TestRunner PASS cases and 5037 assertions. The dynamic matrix varies `NULLS FIRST/LAST`, numeric RANGE boundaries, duplicate numeric peers, NULL peer labels, and upstream t1 value spacing while checking `SQLiteWindowFunction::aggregateOrderedRangeValues()` against an independent oracle.
- Non-overlap: this targets `windowA.test` RANGE/NULL-placement aggregate window behavior only. It avoids accepted `window1/window2` dynamic frame coverage, `window3/window4/window5/window6/window8/window9/windowB/windowC/windowD/windowE/windowerr` batches, JSON/WAL/B-tree/VFS/planner surfaces, and metadata-only upstream runner admission rows.
- Dependency closure: no new support component is needed; this reuses the lane-local window aggregate helper and TestRunner harness.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowARangeNullsDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowARangeNullsDynamicTest.php` -> `1 test files, 5037 assertions, 0 failures`.
