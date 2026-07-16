# real-upstream-corpus-date-affinity-dynamic-20260530T234040Z-0

## Scope

- Base accepted HEAD: `e26da88382a9c31477121cff98ca70bfba38b5f3`.
- Added focused PHP coverage for real upstream SQLite `date.test`:
  - `date-2.1` numeric `datetime(0,'unixepoch')`
  - `date-2.2b` text `datetime('946684800','unixepoch')`
  - `date-2.2c-0..999` fractional millisecond `strftime('%H:%M:%f',1237962480.xxx,'unixepoch')`
- Non-overlap: this slice does not touch the existing Julian-week `date.test` 3.11 dynamic corpus.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicUnixepochFraction20260530T234040ZTest.php`
  - Result: `1 test files, 3004 assertions, 0 failures`
  - PASS lines: `1001`
- Expected selected movement after acceptance: `1158670 -> 1159671` PHP PASS lines.
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`.

## Dependency closure

No new support component is needed. The test reuses the existing local `sqlite3` oracle pattern already used by real-upstream dynamic corpus tests, and exercises the native PHP `SQLiteCoreScalarFunction` date/time implementation.
