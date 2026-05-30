# Real Upstream Date Affinity UTC/NULL Dynamic Slice

- Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T194100Z-0`
- Base accepted HEAD: `bc1638b6eb86853297e97bc15107a4f4f8e9ef19`
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Upstream sections: `date-5.5`, `date-5.7`, `date-5.13..5.15`, `date-6.25.1..6.25.7`, `date-6.26`, `date-6.27`, and `date-7.1..7.16`.

## Behavior

`SQLiteCoreScalarFunction::sqlFunctionArguments()` now preserves parsed date/time values when the remaining modifier is `utc` or `localtime`. This fixes the upstream `date.test date-6.27` behavior where `datetime('2000-10-29 12:00:00+05:00', 'utc')` returns `2000-10-29 07:00:00` instead of `NULL`.

The new focused test file adds:

- invalid timezone suffix NULL cases from `date-5`;
- explicit UTC suffix/offset no-op cases from `date-6.25..6.27`;
- NULL argument propagation from `date-7`;
- 1200 dynamic timezone-qualified day/offset cases derived from the upstream UTC-offset sections;
- one generic application schedule-normalization case with no domain-specific API names.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicUtcNullTest.php`
  - `1 test files, 4873 assertions, 0 failures`
  - `1232` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicUtcNullTest.php`
  - `2 test files, 31704 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicUtcNullTest.php`
  - no syntax errors

`lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is absent in this worktree, so the no-domain API guard was not run.

## Non-Overlap

This slice does not repeat accepted date5 Gregorian-cycle, date4 strftime, date3 auto-boundary, date2 schema determinism, date floor/ceiling, or date modifier arithmetic batches. It targets upstream timezone/UTC modifier and NULL propagation behavior not covered by the existing accepted date-affinity files.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP scalar date/time implementation and focused TestRunner.
