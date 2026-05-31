# real-upstream-corpus-window-functions-dynamic-20260531T052529Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test`.
- Ported sections: `windowC.test` 1 row-varying `group_concat('val', x)` window separators and 2.0-2.1 UTF-16 separator/value regression.
- Added focused file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorDynamicTest.php`.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorDynamicTest.php` passes with `1 test files, 1005 assertions, 0 failures`.
- Non-overlap: this extends `windowC.test` row-varying separator behavior and avoids accepted window3 matrix, windowE custom collation, windowfault large frame, JSON filtered window, pushdown, no-ORDER RANGE/GROUPS, and row-value/window current-source surfaces.
- Dependency closure: no new support component needed; the slice reuses existing native `SQLiteWindowFunction::groupConcatFrameBetweenSeparators()` and `SQLiteBlobValue`.
- Root harness: not run; isolated micro-slice.
