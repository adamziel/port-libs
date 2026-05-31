# real-upstream-corpus-date-affinity-dynamic-20260531T034843Z-0

- Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Owned range: `date4.test` loop rows `13300..14299`, where upstream computes `TS = i * 86390` and checks `SELECT strftime($::FMT,$::TS,'unixepoch')` against libc `strftime`.
- Focused movement: `1004` TestRunner PASS cases, `6015` behavior assertions.
- Non-overlap: starts after accepted `date4.test` rows through `13299`; avoids date/date2/date3/date5 modifier coverage, floor/ceiling, invalid strftime conversion, date20 no-round, and affinity comparison/type matrix clusters.
- Dependency closure: no new support component needed; this reuses `SQLiteCoreScalarFunction` `strftime`/`typeof` dispatch and PHP UTC date formatting for expected-value construction.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows13300To14299Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows13300To14299Test.php` passed: `1 test files, 6015 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.
