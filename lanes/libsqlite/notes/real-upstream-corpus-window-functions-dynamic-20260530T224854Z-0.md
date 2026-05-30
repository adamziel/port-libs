## real-upstream-corpus-window-functions-dynamic-20260530T224854Z-0

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Added `SQLiteRealUpstreamWindow4NavigationDynamicTest.php` with 1,001 distinct focused TestRunner PASS cases and 2,001 assertions from real upstream SQLite window navigation behavior.

Upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` section 1 `ntile(...) OVER (ORDER BY a)`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test` lead/lag/nth_value navigation sections around 4.1-4.7
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` section 7.2-7.4 lead and row_number window navigation coverage

Non-overlap:

- This does not add another window9 filtered-min/collation slice.
- This does not repeat the existing window7/window8 GROUPS/RANGE dynamic corpus.
- This does not touch production code, generated denominator rows, fake upstream script ids, WordPress-specific APIs, or metadata-only admission records.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow4NavigationDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4NavigationDynamicTest.php` passed: `1 test files, 2001 assertions, 0 failures`.
- Expected PASS-line movement: `+1001` distinct TestRunner PASS cases.

Dependency closure:

- No new support component is needed; the batch reuses the existing `SQLiteWindowFunction` navigation helpers and TestRunner harness.
