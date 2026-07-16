# real-upstream-corpus-date-affinity-dynamic-20260601T151644Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip20260601T151644ZTest.php` as an additive real upstream date/affinity corpus batch.

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream scenario range: `atof1.test` `atof1-1.1.1/.2` through `atof1-1.1200.1/.2`
- Upstream behavior: the seeded `expr srand(1)` loop creates random floating-point values, formats each as `%.32e`, verifies the text literal converts to the same REAL as the bound double, and verifies `CAST(quote($x) AS real)` preserves the original REAL.

## Behavior

- Ports 1,200 distinct seeded upstream `atof1-1` rows using `tclsh` to replay the exact upstream random sequence and `sqlite3` as the local oracle.
- Each row verifies native `SQLiteSelectSql` TEXT-to-REAL `CAST`, REAL storage class, formatted sqlite3 parity, text literal equality with the bound Tcl double, native double-bit preservation, and `quote()` / `CAST(... AS REAL)` round-trip preservation.
- Tightens finite REAL quote formatting in `SQLiteCoreScalarFunction` and `SQLiteRealExpressionAffinityCorpusPlan` from 15 to 17 significant digits so quoted finite doubles round-trip exactly.
- Includes a generic `app_numeric_metrics` rollup; no domain-specific libsqlite API or fixture name was added.

## Focused Growth

- Focused TestRunner PASS cases added by this file: `1203`
- Focused behavior assertions in this file: `16823`
- Expected `phpPass` movement if accepted alone: `5976232 -> 5977435`
- Mapped denominator movement: none; libsqlite already records `1589 / 1589`.

## Non-Overlap

- Owns only upstream `atof1.test` `atof1-1.1..1200` seeded text-to-REAL and quote round-trip rows.
- Avoids accepted `atof1.test` `atof1-2` UTF-16/blob rows, `atof-3.1` integer-prefix suffixes `0592..1609`, `atof-3.2` decimal suffixes `0000..9999`, `atof-3.3` exponent rows, `atof2` rounding, saturated `date4` row ranges, timediff matrices, `affinity3`, and types storage-class batches.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip20260601T151644ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1RandomRoundtrip20260601T151644ZTest.php`
  - `1 test files, 16823 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1*Test.php`
  - `14 test files, 185669 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityBoundaryOracleCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicOracleBatchTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicFollowupCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealOracleTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityTypes2OracleDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTypes2TextAffinityDynamicBulkTest.php`
  - `7 test files, 37594 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealConversionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php`
  - `4 test files, 25393 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no output, exit `0`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses hydrated upstream `atof1.test`, existing local `tclsh` and `sqlite3` oracle tooling, `SQLiteSelectSql` CAST/equality/function dispatch, `SQLiteCoreScalarFunction` `quote`/`format`, and `SQLiteRealExpressionAffinityCorpusPlan` REAL casting/storage helpers.
