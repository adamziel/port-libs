# real-upstream-corpus-date-affinity-dynamic-20260530T195102Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date-20.1..20.4`: fractional-second tails such as `.9995` and long `.9999999999999` are accepted and truncated for `datetime()`, not rounded into the next second or rejected.

Focused coverage:

- Added `SQLiteRealUpstreamDateFractionTruncationDynamicCorpusTest.php`.
- Ports the four explicit upstream `date-20` rows and a 1000-row dynamic matrix derived from that upstream section across different dates/times and long fractional tails.
- Focused result: 1006 distinct TestRunner PASS cases and 10019 assertions in the new test file.
- Non-overlap: existing date corpus files cover date2 deterministic schema guards, date3 auto/unixepoch boundaries, date4 strftime parity, date5 Gregorian cycles, date-16 boundaries, and date-19 floor/ceiling. This slice owns only date-20 long fractional-second truncation.

Implementation:

- `SQLiteCoreScalarFunction` now accepts date/time fractional seconds longer than six digits and truncates them before PHP `DateTimeImmutable` parsing, matching SQLite's date-20 behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateFractionTruncationDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFractionTruncationDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php`
- `if [ -f lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php ]; then php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php; else echo 'no guard file'; fi`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The slice reuses the existing native date/time scalar parser and only fixes fractional-second parsing inside `SQLiteCoreScalarFunction`.
