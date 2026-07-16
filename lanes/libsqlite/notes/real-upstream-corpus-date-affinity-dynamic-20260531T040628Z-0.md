# real-upstream-corpus-date-affinity-dynamic-20260531T040628Z-0

- Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`.
- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15399Test.php`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Owned range: `date4.test` loop rows `14300..15399`, where upstream computes `TS = i * 86390` and checks `SELECT strftime($::FMT,$::TS,'unixepoch')` against libc `strftime`.
- Focused movement: `1104` TestRunner PASS cases, `6615` behavior assertions.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15399Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows14300To15399Test.php` passed: `1 test files, 6615 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.

Non-overlap:

- This starts after accepted `date4.test` rows through `14299`.
- It avoids accepted date/date2/date3/date5 modifier coverage, date4 real-date/no-round batches, floor/ceiling/month-matrix/invalid-strftime coverage, and expression-affinity comparison/type-matrix clusters.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this handoff should count as PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteCoreScalarFunction` `strftime`/`typeof` dispatch and PHP UTC date formatting for expected-value construction.
