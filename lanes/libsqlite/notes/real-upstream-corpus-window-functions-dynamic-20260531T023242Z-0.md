# real-upstream-corpus-window-functions-dynamic-20260531T023242Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Ported sections: `window4` `1.1-1.19`, `2.1-2.4.1`, and `3.1-3.2`.

Coverage added:

- `SQLiteRealUpstreamWindow4NtileValueDynamicTest.php`
- Static upstream assertions for `ntile()`, `nth_value()` with per-row indexes, `lead()`, `lag()`, and `group_concat()` over `ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING`.
- 1,000 dynamic upstream-shaped `ntile()` distribution cases covering row counts 3-31 and bucket counts 1-37.

Non-overlap:

- This slice uses `window4.test` value and distribution behavior. It avoids the existing accepted `window9` collation/FILTER dynamic min work, `windowB` JSON-object inverse behavior, mixed-type REAL RANGE behavior, and existing rowvalue/RETURNING window slices.

Dependency closure:

- No new support component is needed. The slice reuses the existing native `SQLiteWindowFunction` helper surface and focused PHP `TestRunner`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow4NtileValueDynamicTest.php`
  - `1 test files, 3049 assertions, 0 failures`
  - 1,008 focused PASS lines
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow4NtileValueDynamicTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
- `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run; guard file is not present in this accepted-base worktree
