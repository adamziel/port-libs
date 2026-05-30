Real upstream corpus window3 dynamic matrix slice, 2026-05-30.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- Upstream scenario family: generated `window3.test` dynamic sections covering partitioned window functions across ORDER BY terms, ROWS/GROUPS/RANGE frames, EXCLUDE modes, ranking functions, aggregate windows, and value windows.

Implemented lane-local coverage:
- Added `SQLiteRealUpstreamWindow3DynamicMatrixExpandedTest.php` as a 1001-case real upstream matrix, preserving the accepted `SQLiteRealUpstreamWindow3DynamicMatrixTest.php` file unchanged:
  - 640 aggregate window cases over `count`, `sum`, `total`, `avg`, `min`, `max`, and `group_concat`.
  - 240 value window cases over `first_value`, `last_value`, and row-dependent `nth_value`.
  - 120 ranking cases over `row_number`, `rank`, `dense_rank`, `percent_rank`, and `cume_dist`.
  - 1 source-note case preserving the exact upstream file/scenario family and distinct case count.
- The tests use an independent oracle for frame membership, peer groups, EXCLUDE behavior, partition ordering, and result types, then compare against `SQLiteWindowFunction`.

Non-overlap:
- This batch targets `window3.test` dynamic matrix behavior. It does not repeat the existing `window4`, `window7`, `window8`, `windowA`, or `windowB` dynamic frame/range/null files, and it avoids metadata-only runner admission rows.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow3DynamicMatrixTest.php`
  - `1 test files, 16004 assertions, 0 failures`

Dependency closure:
- No new support component is needed. The slice reuses the existing native `SQLiteWindowFunction` frame, aggregate, value, and ranking helpers.
