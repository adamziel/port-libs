# real-upstream-corpus-date-affinity-dynamic-20260531T021122Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows8300To9299Test.php` as an additive real upstream `date4.test` batch.

## Upstream source

- File: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-$i` loop with `SELECT strftime($::FMT,$::TS,'unixepoch')`
- Owned range: `date4-08300` through `date4-09299`
- Focused TestRunner growth: `1003` PASS cases
- Behavior assertions: `6014`

## Non-overlap

This slice starts immediately after the accepted/current `date4` coverage through `date4-08299`. It does not touch accepted date/date2/date3/date5 modifier, deterministic schema, Unix epoch, Julian day, affinity comparison, or type-matrix surfaces.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows8300To9299Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows8300To9299Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows8300To9299Test.php`
  - `1 test files, 6014 assertions, 0 failures`
  - `1003` PASS lines

## Dependency closure

No new support component is needed. The batch reuses the existing native PHP date/time scalar function implementation and the hydrated upstream SQLite checkout as source truth.
