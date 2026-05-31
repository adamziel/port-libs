# real-upstream-corpus-date-affinity-dynamic-20260531T155623Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix3000To3999Test.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Scenario: `atof-3.2`
- Owned range: decimal REAL suffixes `3000..3999` from the upstream recursive `format('18.44674407370955%04d',i+1)` matrix.

Focused behavior:

- Verifies parser-level `CAST(text AS REAL)` conversion for 1000 decimal mantissa values beginning with `18.44674407370955`.
- Cross-checks native `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL insert affinity, `quote()`/CAST round-trip preservation, and `format('%.10e', ...)` output against the local `sqlite3` oracle.
- Adds a generic `app_decimal_metrics` rollup that exercises the same storage-facing REAL conversion path without domain-specific APIs.

Focused evidence:

- New TestRunner PASS cases: `1003`.
- Focused behavior assertions: `14022`.
- Expected selected evidence movement: `phpPass` `3137763 -> 3151785`.
- Mapped denominator movement: none; upstream denominator is already `1589 / 1589`.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix3000To3999Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix3000To3999Test.php` => `1 test files, 14022 assertions, 0 failures`

Non-overlap:

- This owns only upstream `atof1.test` `atof-3.2` decimal REAL suffixes `3000..3999`.
- It avoids accepted `atof-3.2` suffixes `0000..2999`, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, `atof2` rounding rows, date4 ranges, date/timediff matrices, expression-affinity shards, and types storage-class batches.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteRealExpressionAffinityCorpusPlan`, `SQLiteCoreScalarFunction`, and the hydrated upstream SQLite checkout plus local `sqlite3` oracle as source-truth evidence.
