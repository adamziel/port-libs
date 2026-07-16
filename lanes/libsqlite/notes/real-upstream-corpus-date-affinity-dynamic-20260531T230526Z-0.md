# real-upstream-corpus-date-affinity-dynamic-20260531T230526Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix6000To6999Test.php` as an additive real upstream date/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Scenario: `atof-3.2`, the recursive decimal text `CAST(vtxt AS REAL)` guard for `18.44674407370955%04d`.

Focused behavior:

- Owns the non-overlapping `atof-3.2` decimal REAL suffix window `6000..6999`.
- Verifies 1,000 dynamic suffixes against a local `sqlite3` oracle for `typeof(CAST(... AS REAL))`, formatted REAL output, upstream `GLOB '18.446744073709*'` admission, and `quote()` / `CAST(... AS REAL)` round-trip parity.
- Exercises the native `SQLiteSelectSql` scalar dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity storage, `SQLiteCoreScalarFunction` `format()` / `quote()` behavior, and a generic `app_decimal_metrics` rollup.

Non-overlap:

- Existing accepted local shards own `atof-3.2` suffixes `0000..4999`.
- The newest ready date-affinity marker owns `atof-3.2` suffixes `5000..5999`; this handoff deliberately skips that queued range.
- This does not repeat accepted `atof-3.1` integer-prefix suffixes `0592..1609`, `atof2` rounding, `date4` row ranges, date/timediff matrix work, or types storage-class affinity batches.
- Expected dashboard movement is focused TestRunner PASS-case growth only: `phpPass` `4198004 -> 4199007` if accepted alone. Mapped denominator remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix6000To6999Test.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix6000To6999Test.php` - `1 test files, 14022 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalMantissa20260531T115519ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix1000To1999Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix2000To2999Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix3000To3999Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix4000To4999Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix6000To6999Test.php` - `6 test files, 82123 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - passed.
- `git diff --check -- lanes/libsqlite` - passed.

Root harness:

- Not run - isolated micro-slice.

Dependency closure:

- No new support component is needed. The slice reuses existing native SELECT CAST/GLOB/function dispatch, REAL affinity storage, scalar format/quote behavior, and the already-available local `sqlite3` oracle used by adjacent accepted upstream corpus shards.
