# real-upstream-corpus-date-affinity-dynamic-20260531T051913Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows19400To20399Test.php`
as an additive real upstream date/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario range: `date4.test` loop rows `date4-19400` through `date4-20399`.
- Upstream loop cited in-test: `for {set i 0} {$i<=24858} {incr i}` with
  `SELECT strftime($::FMT,$::TS,'unixepoch');`.

Focused coverage:

- 1,000 dynamic real upstream `strftime()` unixepoch cases over the upstream
  date4 format matrix:
  `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.
- Each dynamic case validates integer, text, and REAL timestamp affinity
  dispatch through `SQLiteCoreScalarFunction`, result storage class, comma
  shape, date prefix, and ISO week/year suffix.
- Additional source-citation, generic retention rollup, non-overlap, and
  dependency-closure cases bring the focused TestRunner PASS count to 1,004.

Non-overlap:

- Owns only `date4.test` rows `19400..20399`.
- Avoids accepted date4 rows `0..19399`, date/date2/date3/date5 modifier and
  calendar coverage, expression-affinity comparison/type matrix work, and all
  runner metadata-only admission rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows19400To20399Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows19400To20399Test.php`
  - `1 test files, 7015 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses native
  `SQLiteCoreScalarFunction` strftime/unixepoch parsing, date formatting, and
  type-affinity coercion.
