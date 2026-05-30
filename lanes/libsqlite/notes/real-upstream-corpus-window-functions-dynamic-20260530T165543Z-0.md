# Real upstream corpus: dynamic window functions

Slice: `real-upstream-corpus-window-functions-dynamic-20260530T165543Z-0`

Base accepted HEAD: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`

Upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  - Ported scenarios `1.1` through `1.19` for `ntile(N)` bucket distribution.
  - Ported scenarios `2.1` through `2.4.1` for dynamic `nth_value(b,c)`, `lead()`, `lag()`, and following-frame `group_concat()`.
  - Ported scenarios `3.1` through `3.6.3` for dynamic `nth_value()` over peer groups, partition restart, named opposing windows, filtered max, and reversed/empty ROWS boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
  - Ported scenarios `3.1`, `4.1`, `4.2`, `5.1`, and `5.2` for numeric RANGE max propagation, large-integer `total()`, and mixed integer/real frame sums.

Implementation:

- Added `SQLiteWindowFunction::nthValueByRow()` for row-dependent `nth_value()` indexes over explicit ROWS/RANGE/GROUPS frames.
- Added focused real corpus tests in `SQLiteWindowDynamicUpstreamCorpusTest.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php` passed: `1 test files, 603 assertions, 0 failures`, `45` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindow*.php` passed: `9 test files, 1028 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` was not runnable because that guard file is absent in this worktree.

Dashboard movement:

- Expected `phpPass` movement: `+45` focused PASS lines, from `198691` to `198736`.
- Mapped denominator movement: none claimed. These are behavior assertions ported from already hydrated upstream `.test` files, not new denominator inventory rows.

Dependency closure:

- No new support component needed. The slice reuses the existing native PHP `SQLiteWindowFunction` helper and the repo `TestRunner`.

Non-overlap:

- This does not repeat accepted window ROWS/RANGE/GROUPS frame hardening or no-ORDER-BY rejection. The new behavior is row-dependent `nth_value()` plus real upstream dynamic `window4.test`/`windowE.test` result rows.
