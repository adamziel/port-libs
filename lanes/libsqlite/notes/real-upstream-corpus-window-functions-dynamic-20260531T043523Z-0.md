# real-upstream-corpus-window-functions-dynamic-20260531T043523Z-0

- Base accepted HEAD: `7db59d242cf2590641e3217c1b87d71727256c92`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`.
- Ported scenario range: `windowpushd.test` `1.0-1.4`, `2.0-2.1.5`, `2.2.1-2.4.3`.
- Added focused PHP corpus: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php` passed with `1 test files, 14831 assertions, 0 failures`.
- PASS-line growth expected for integrator counting: `+2077` focused TestRunner PASS cases in this file.
- Non-overlap: targets upstream `windowpushd.test` view/subquery window-function pushdown behavior, not accepted `window1`/`window2` frame batches, `window3` ranking/value matrices, `window4` value functions, `window7` GROUPS/RANGE, `window9`, `windowD`, `windowE`, or JSON window aggregate handoffs.
- Dependency closure: no new support component needed; this reuses existing `SQLiteWindowFunction` row-number and aggregate helpers.
- Root harness: not run from this isolated micro-slice.
