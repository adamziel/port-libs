# real-upstream-corpus-window-functions-dynamic-20260531T013046Z-0

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  - Offset-value family reference: `window1-6.3` lead/lag FILTER misuse boundary and related offset-value window behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
  - Dynamic generated families: `window3-1.1.7` `ntile()` bucket distribution and adjacent offset-value generated window families.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindowLeadLagNtileDynamicTest.php`.
- The file contributes `1001` distinct TestRunner PASS cases and `1004` focused assertions.
- Coverage matrix:
  - `360` generated `lead()` cases.
  - `360` generated `lag()` cases.
  - `280` generated `ntile()` cases.
  - `1` source/countability note case.

## Non-Overlap

This does not repeat the accepted window1 sales corpus, the existing
`SQLiteRealUpstreamWindow3DynamicMatrixExpandedTest.php` aggregate/value/ranking
frame matrix, or the existing window2/window7/window9/windowD/windowE frame,
collation, group-range, and boolean-view corpus files. It exercises a separate
upstream window offset-value and bucket-distribution surface against
`SQLiteWindowFunction::lead()`, `lag()`, and `ntile()`.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowLeadLagNtileDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowLeadLagNtileDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowLeadLagNtileDynamicTest.php`
  - `1 test files, 1004 assertions, 0 failures`
  - `1001` PASS lines.

## Dependency Closure

No new support component is needed. This reuses the existing native
`SQLiteWindowFunction` helper and the existing focused PHP TestRunner.
