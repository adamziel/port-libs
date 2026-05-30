# Real Upstream Window C Separator Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-window-20260530T210926Z`
Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`

This slice ports real upstream SQLite `windowC.test` varying `group_concat()`
separator window behavior into a focused PHP TestRunner file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test`
- Sections: `1.text.1-1.text.5`, `1.blob.1-1.blob.5`, and cited
  UTF-16 separator edge sections `2.0-2.1`

Added focused coverage:

- `SQLiteRealUpstreamWindowCSeparatorDynamicTest.php`
- `1006` distinct TestRunner PASS cases
- `5031` focused behavior assertions
- Non-overlap: this targets row-varying `group_concat()` separators from
  `windowC.test`; it avoids accepted `windowB` JSON/range rows,
  `windowD` truth semantics, `windowE` collation RANGE behavior, windowerr
  invalid-frame guards, window1/window2 frame summaries, and runner metadata.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowCSeparatorDynamicTest.php`
  passed with `1 test files, 5031 assertions, 0 failures`.

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLiteWindowFunction::groupConcatFrameBetweenSeparators()`
helper and independent PHP oracle assertions.
