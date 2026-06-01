# real-upstream-corpus-date-affinity-dynamic-20260601T165934Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip1201To2400Test.php` as an additive real upstream date/affinity corpus batch.

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream scenario range: `atof1.test` `atof1-1.1201.1/.2` through `atof1-1.2400.1/.2`
- Upstream behavior: the seeded `expr srand(1)` loop creates random floating-point values, formats each as `%.32e`, verifies the text literal converts to the same REAL as the bound double, and verifies `CAST(quote($x) AS real)` preserves the original REAL.

## Behavior

- Ports 1,200 distinct seeded upstream `atof1-1` continuation rows using `tclsh` to replay the exact upstream random sequence and `sqlite3` as the local oracle.
- Each row verifies native `SQLiteSelectSql` TEXT-to-REAL `CAST`, REAL storage class, formatted sqlite3 parity, text literal equality with the bound Tcl double, native double-bit preservation, and `quote()` / `CAST(... AS REAL)` round-trip preservation.
- Includes a generic `app_numeric_metrics` rollup; no domain-specific libsqlite API or fixture name was added.

## Focused Growth

- Focused TestRunner PASS cases added by this file: `1203`
- Focused behavior assertions in this file: `16826`
- Expected `phpPass` movement if accepted alone: `6150052 -> 6151255`
- Mapped denominator movement: none; libsqlite already records `1589 / 1589`.

## Non-Overlap

- Owns only upstream `atof1.test` `atof1-1.1201..2400` seeded text-to-REAL and quote round-trip rows.
- Extends the accepted `atof1-1.1..1200` random REAL shard without duplicating it.
- Avoids accepted `atof1.test` `atof1-2` UTF-16/blob rows, `atof-3.1` integer-prefix suffixes, `atof-3.2` decimal suffixes, `atof-3.3` exponent rows, `atof2` rounding, saturated `date4` row ranges, timediff matrices, `affinity2`, `affinity3`, and types storage-class batches.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip1201To2400Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip1201To2400Test.php`
  - `1 test files, 16826 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip20260601T151644ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip1201To2400Test.php`
  - `2 test files, 33649 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses hydrated upstream `atof1.test`, existing local `tclsh` and `sqlite3` oracle tooling, `SQLiteSelectSql` CAST/equality/function dispatch, `SQLiteCoreScalarFunction` `quote`/`format`, and `SQLiteRealExpressionAffinityCorpusPlan` REAL casting/storage helpers.
