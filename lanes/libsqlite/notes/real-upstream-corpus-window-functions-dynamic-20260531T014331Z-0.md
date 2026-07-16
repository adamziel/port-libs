# real-upstream-corpus-window-functions-dynamic-20260531T014331Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`.

Ported sections:

- `window6.test` 5.0-5.5 keyword identifiers around `over` and `window`.
- `window6.test` 8.1-8.3 sample ranking and ROWS frame sums.
- `window6.test` 9.0 recursive ROWS `group_concat()` and 9.3-9.8 frame/error guards.
- `window6.test` 10.0-10.2 FILTER and `nth_value()` argument coercion.
- `window6.test` 11.2-11.4 scalar lookup with cumulative windows, zero-boundary aliases, and text RANGE peer fallback.

Behavior delta:

- `SQLiteWindowFunction::nthIndexValue()` now accepts numeric strings with a zero fractional part, matching upstream `nth_value(b, '2.0')`.
- Added `SQLiteRealUpstreamWindow6DynamicTest.php` with static upstream cases plus 260 dynamic ROWS/FILTER/nth-value cases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow6DynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6DynamicTest.php` passed: 1 file / 6643 assertions / 0 failures / 284 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 1 file / 3 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow9CollationFilterDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6DynamicTest.php` passed: 3 files / 11694 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement: +284 selected PHP PASS lines, from 1542646 to 1542930 if accepted; mapped coverage remains 1589 / 1589.

Dependency closure: no new support component needed; this reuses lane-local `SQLiteWindowFunction` frame, aggregate, ranking, FILTER, and `nth_value()` helpers.

Non-overlap: this avoids the already accepted `window9`, `window7`, `windowE`, grouped SELECT text, expression ORDER BY, JSON table, B-tree, WAL, VFS, and source-neutral clusters by targeting upstream `window6.test` keyword/coercion/frame behavior.
