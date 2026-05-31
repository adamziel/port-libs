# Real upstream window dynamic corpus 2026-05-31

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T011705Z-0`

Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Scenarios: `window1.test` 10.1 through 10.7, especially best-two salespeople by `row_number()`, `sum(total) OVER win` partition running totals, current-row-to-unbounded-following frames, and `LIMIT/OFFSET` applied after window values are computed.

Lane changes:

- Added `lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusSalesTest.php`.
- Added 1003 distinct focused TestRunner PASS cases.
- Added 13503 behavior assertions.
- Mapped denominator remains `1589 / 1589`; this is selected PHP PASS-line growth, not mapped denominator growth.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusSalesTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusSalesTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusSalesTest.php`
  - `1 test files, 13503 assertions, 0 failures`
  - 1003 PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- Does not touch accepted JSON table source/cursor/hidden/visible constraints, B-tree page move/root-collapse/overflow release, WAL checkpoint/savepoint/rollback application, VFS writer/lock/sync, grouped SELECT SQL text, expression ORDER BY, or previous window dynamic frame/custom aggregate corpus files.
- This slice is specifically upstream `window1.test` sales/window partition behavior, using generic employee/region rows and no new domain-specific API.

Dependency closure:

- No new support component is needed. The slice reuses the existing native `SQLiteWindowFunction` helpers for row-number ranking and frame aggregate behavior.

Root harness:

- Not run; isolated micro-slice.
