# real-upstream-corpus-date-affinity-dynamic-20260601T075148Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix9000To9999Test.php` as an additive real upstream date/affinity corpus batch.

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream scenario: `atof1.test` `atof-3.2`
- Owned range: decimal REAL suffixes `9000..9999` from the upstream recursive `format('18.44674407370955%04d',i+1)` matrix.

## Behavior

- Ports 1,000 distinct upstream decimal text to REAL conversion rows.
- Each row verifies native `SQLiteSelectSql` `CAST(vtxt AS REAL)` over TEXT-affinity input, SQLite storage class, `format('%.10e', ...)`, and the upstream `GLOB '18.446744073709*'` admission guard against a local `sqlite3` oracle.
- Each row also verifies native REAL affinity storage and `quote()` / `CAST(... AS REAL)` round-trip preservation.
- Includes a generic `app_decimal_metrics` rollup; no domain-specific libsqlite API or fixture name was added.

## Focused Growth

- Focused TestRunner PASS cases: `1003`
- Focused behavior assertions: `14022`
- Expected `phpPass` movement if accepted alone: `5698998 -> 5700001`
- Mapped denominator movement: none; libsqlite already records `1589 / 1589`.

## Non-Overlap

- Avoids accepted `atof1.test` `atof-3.2` decimal REAL/mantissa suffixes `0000..4999` and `6000..8999`.
- Avoids the historical pending-ready `5000..5999` suffix shard mentioned by adjacent accepted corpus files.
- Avoids accepted `atof-3.1` integer-prefix suffixes `0592..1609`, accepted `atof-3.3` exponent rows, `atof2` rounding, saturated `date4` row ranges, date/timediff matrices, and types storage-class batches.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix9000To9999Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix9000To9999Test.php`
  - `1 test files, 14022 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no output, exit `0`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity/storage helpers, `SQLiteCoreScalarFunction` `quote`/`format`, hydrated upstream source text, and the local `sqlite3` oracle used by adjacent accepted upstream corpus shards.
