# real-upstream-corpus-window-functions-dynamic-20260530T215143Z-0

- Base accepted HEAD: `4d354e3a7fdb39040e393b5132f7de86a7766ad9`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.
- Ported sections: `window2.test` `2.14` through `2.24`, covering `ROWS` frame sums with empty preceding frames, following-only frames, `UNBOUNDED FOLLOWING`, partitioned `b` frames, and parity partitions.
- New focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow2RowsFollowingDynamicTest.php`.
- Focused PASS growth: 1,069 distinct TestRunner cases in the new file, including 1,002 generated dynamic real-upstream ROWS-frame cases plus explicit upstream example rows and source citation.
- Non-overlap: existing accepted window files cover `window2.test` partitioned `RANGE` aggregation, `window4` value/ntile frames, `window7/8` group/range cases, `window9` collation/filter cases, and `windowA-E` ordered/collation/JSON/group-concat behavior. This slice is limited to `window2.test` `ROWS BETWEEN` following/preceding/unbounded boundary behavior.
- Dependency closure: no new support component needed; this reuses the lane-local `SQLiteWindowFunction::aggregateFrameBetweenValues()` ROWS-frame executor.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow2RowsFollowingDynamicTest.php` passed with no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow2RowsFollowingDynamicTest.php` passed: `1 test files, 1070 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
  - `git diff --check -- lanes/libsqlite` passed.
