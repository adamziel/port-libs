# real-upstream-corpus-date-affinity-dynamic-20260531T220652Z-0

## Upstream Source Truth

- Source file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream scenario: `do_execsql_test atof-3.2`
- Owned range: decimal REAL suffixes `4000..4999` from upstream `format('18.44674407370955%04d',i+1)`.
- Ported behavior: `CAST(vtxt AS REAL)` over long decimal text must produce REAL storage, match SQLite's `format('%.10e', ...)` oracle, satisfy the upstream `GLOB '18.446744073709*'` guard, and preserve quote/REAL-affinity round trips.

## Focused Evidence

- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix4000To4999Test.php`
- Focused PASS cases: `1003` (`1` source-truth case, `1000` suffix cases, `1` generic application rollup, `1` non-overlap/dependency case)
- Behavior assertions: `14022`
- Expected `phpPass` movement if accepted: `3849202 -> 3850205`
- Mapped denominator movement: none; this burns down an already hydrated `atof1.test` behavior range.

## Non-Overlap

This shard avoids accepted `atof1.test` `atof-3.2` decimal REAL suffixes `0000..3999`, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, `atof2` rounding slices, saturated `date4.test` rows, date/timediff matrices, and types storage-class batches.

## Dependency Closure

No new support component is needed. The test reuses existing `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity/storage helpers, `SQLiteCoreScalarFunction` `quote`/`format`, and the local `sqlite3` oracle.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix4000To4999Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix4000To4999Test.php`
  - `1 test files, 14022 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json OK\n";'`
  - `lane-status.json OK`
- `git diff --check -- lanes/libsqlite`
  - no output

Root harness was not run for this isolated micro-slice.
