# Real Upstream Corpus Date Affinity Timezone Dynamic 20260530T230804Z

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T230804Z-0`

Accepted base: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date.test` `date-5.1..5.15`: timezone suffix parsing, UTC normalization, and malformed suffix rejection.
- `date.test` `date-6.25.1..6.25.7`: `Z`, `+00:00`, and `-00:00` suffixes are already UTC and repeated `utc` modifiers are no-ops.
- `date.test` `date-6.26..6.27`: non-zero timezone suffixes normalize to UTC and stay normalized after a following `utc` modifier.

Patch scope:

- Added `SQLiteRealUpstreamCorpusDateAffinityTimezoneDynamic20260530T230804ZTest.php`.
- No production change was needed; existing `SQLiteCoreScalarFunction` date/time parsing already satisfied the upstream timezone behavior.
- The generated 1,000-row matrix uses distinct local datetimes and timezone offsets to exercise the same upstream timezone-normalization rule through `datetime`, `date`, `time`, and `typeof`.

Non-overlap:

- Does not repeat the accepted millisecond unixepoch, `date2` deterministic schema/index, date modifier-row, localtime transition/failure, subsecond, Gregorian-cycle, floor/ceiling, date3 auto/unixepoch, or date4 strftime extended batches.
- This slice owns timezone suffix normalization and repeated `utc` no-op behavior only.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityTimezoneDynamic20260530T230804ZTest.php`
- Result: `1 test files, 6061 assertions, 0 failures`

Dependency closure:

- No new support component required; reused the existing pure-PHP scalar date/time implementation.
