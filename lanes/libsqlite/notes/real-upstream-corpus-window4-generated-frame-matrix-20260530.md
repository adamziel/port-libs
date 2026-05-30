## real-upstream-corpus-window-functions-dynamic-20260530T192901Z-0

- Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`.
- Ported section: `window4.test` `4.5.*` generated two-window frame matrix over table `ttt`.
- New focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow4GeneratedFrameMatrixTest.php`.
- Focused growth: `18433` distinct TestRunner PASS cases and `36867` assertions in the new file.
- Non-overlap: this extends the existing dynamic window helper batches with the `window4.test` generated pairwise frame matrix across partition specs, `RANGE`/`ROWS` frame boundaries, empty-frame behavior, and paired `max/min` plus `sum/sum` outputs. It does not add runner metadata rows, fake upstream script IDs, WordPress-shaped APIs, or status-only coverage.
- Dependency closure: no new support component is needed; the slice reuses the existing `SQLiteWindowFunction::aggregateFrameBetweenValues()` implementation and an independent lane-local oracle for the upstream matrix.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow4GeneratedFrameMatrixTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4GeneratedFrameMatrixTest.php | tail -40` -> `1 test files, 36867 assertions, 0 failures`.
