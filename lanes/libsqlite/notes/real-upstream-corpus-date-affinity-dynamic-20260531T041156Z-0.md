# real-upstream-corpus-date-affinity-dynamic-20260531T041156Z-0

Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`.

Implemented one real upstream corpus cluster from hydrated SQLite source:

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Upstream scenario: `date4.test` strftime libc parity loop, `for {set i 0} {$i<=24858} {incr i}`
- Owned non-overlapping range: `date4-14300` through `date4-15299`
- Added PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15299Test.php`
- Focused PASS-line growth if accepted: `+1004`
- Focused assertion growth: `+6015`

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15299Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15299Test.php`
  - `1 test files, 6015 assertions, 0 failures`
  - `1004` PASS lines
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

This slice extends the accepted date4 row-range corpus from rows `0..14299` to `14300..15299`. It avoids accepted date/date2/date3/date5 modifier, deterministic schema, floor/ceiling, unixepoch/auto, real-date no-round, component-validation, and affinity comparison/type-matrix coverage.

Dependency closure:

No new support component is needed. The test reuses `SQLiteCoreScalarFunction` `strftime`/`unixepoch` dispatch against the hydrated upstream date4 loop.
