# real-upstream-corpus-date-affinity-dynamic-20260531T032959Z-0

## Scope

- Added focused PHP coverage for real upstream SQLite `test/date4.test`.
- Ported the next non-overlapping `date4.test` loop range: `date4-12300` through `date4-13299`.
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`, loop `for {set i 0} {$i<=24858} {incr i}` with `SELECT strftime($::FMT,$::TS,'unixepoch');`.

## Evidence

- New file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows12300To13299Test.php`.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows12300To13299Test.php`
  - `1 test files, 6014 assertions, 0 failures`
  - 1003 PASS lines: 1000 distinct upstream row cases plus source-loop, rollup, and non-overlap guards.
- PHP lint: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows12300To13299Test.php`
  - `No syntax errors detected`
- Whitespace check: `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Non-Overlap

This owns only `date4.test` rows `12300..13299`. It avoids accepted date4 row ranges `0..12299`, existing `date.test`/`date2.test`/`date3.test`/`date5.test` modifier coverage, expression-affinity/type-matrix coverage, and the recent accepted date floor/ceiling affinity behavior.

## Dependency Closure

No new support component is needed. The tests reuse `SQLiteCoreScalarFunction::sqlFunctionArguments()` for `strftime()`, `unixepoch` numeric/text time values, and `typeof()` text-affinity checks.

## Next Candidate

Continue the same real upstream `date4.test` loop with `date4-13300..14299` if the integrator wants more high-yield non-overlapping date corpus rows.
