# real-upstream-corpus-upsert-returning-dynamic-20260531T103950Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-600`: `INSERT OR IGNORE ... ON CONFLICT(a) DO NOTHING` on a `WITHOUT ROWID` table remains integrity-ok.
  - `upsert1-610`: repeated text-numeric and integer primary-key inputs collapse to one logical key instead of corrupting the unique index.

Implementation:

- `SQLiteUpsertReturningSql` now admits the `INSERT OR IGNORE INTO` spelling before normal UPSERT conflict-arm parsing.
- Added `SQLiteRealUpstreamUpsertReturningOrIgnoreWithoutRowidDynamicTest.php`.
- The new test ports the upstream `upsert1-600/610` behavior through generic `app_ignore` rows and asserts single-row `RETURNING` output for the inserted row plus no `RETURNING` output for the ignored duplicate.

Focused count:

- 1000 dynamic behavior cases plus source-coverage and dependency-closure cases.
- Focused command passed with `1 test files, 11002 assertions, 0 failures`.
- Expected TestRunner PASS-line growth: `+1002`.

Non-overlap:

- This does not repeat accepted UPSERT target-priority, alias/excluded, trigger-order, SELECT-input, `upsert5` arm-priority, or conflict-target `WHERE` batches.
- This slice owns `INSERT OR IGNORE` parser admission for UPSERT RETURNING and the `WITHOUT ROWID` numeric primary-key duplicate suppression derived from `upsert1-600/610`.

Dependency closure:

- No new support component needed. The patch reuses native `SQLiteUpsertReturningSql` parsing, DO NOTHING conflict handling, and existing SQLite-style loose primary-key comparison.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrIgnoreWithoutRowidDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrIgnoreWithoutRowidDynamicTest.php` passed: `1 test files, 11002 assertions, 0 failures`.
