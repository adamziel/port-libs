# real-upstream-corpus-date-affinity-dynamic-20260531T205429Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1ExponentRange20260531T205429ZTest.php` as an additive real upstream date/affinity corpus batch.

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`.
- Scenario: `atof1.test` `atof-3.3`.
- Coverage: recursive CTE rows `n=-200..200` with both `1.8446744073709550592eN` and `1.8446744073709551609eN` boundary mantissas, matching the upstream `format('%.10e', CAST(... AS REAL)) GLOB '1.8446*'` guard.

## Focused Growth

- New focused TestRunner PASS cases: `805`.
- New focused behavior assertions: `10,447`.
- Mapped denominator movement: none; libsqlite is already recorded at `1589 / 1589` mapped upstream rows.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1ExponentRange20260531T205429ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1ExponentRange20260531T205429ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1ExponentRange20260531T205429ZTest.php`
  - `1 test files, 10447 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1ExponentRange20260531T205429ZTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 10450 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no output, exit `0`

## Non-Overlap

This owns only `atof1.test` `atof-3.3` exponent REAL rows. It avoids accepted `atof-3.2` decimal REAL suffixes `0000..3999`, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, `atof2` rounding rows, date4 row ranges, date/timediff matrices, affinity3 JOIN/UNION storage-class parity, and types storage-class batches.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity, `SQLiteCoreScalarFunction` `format()`/`typeof()`, and local `sqlite3` oracle parity against the hydrated upstream test.
