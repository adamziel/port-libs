# real-upstream-corpus-date-affinity-dynamic-20260531T043405Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows16400To17399Test.php` as an additive real upstream `date4.test` continuation batch.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Source loop: `date4.test` `date4-00000` through `date4-24858`, using `SELECT strftime($::FMT,$::TS,'unixepoch');`
- Owned non-overlapping range: `date4-16400` through `date4-17399`

## Focused Growth

- Adds `1004` focused TestRunner PASS cases:
  - `1000` upstream row cases for `strftime()` libc-format parity over integer and text unixepoch inputs.
  - `1` upstream source citation guard.
  - `1` generic retention rollup.
  - `1` non-overlap guard.
  - `1` dependency-closure guard.

## Non-Overlap

This avoids accepted `date4.test` rows `0..16399`, existing date/date2/date3/date5 modifier coverage, and expression-affinity comparison/type-matrix coverage.

## Dependency Closure

No new support component is needed. The batch reuses the existing native `SQLiteCoreScalarFunction` `strftime`/`unixepoch` support.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows16400To17399Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows16400To17399Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows16400To17399Test.php`
  - `1 test files, 6015 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: guard path does not exist in this worktree.
- `git diff --check -- lanes/libsqlite`
  - Passed.
