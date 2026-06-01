# real-upstream-corpus-date-affinity-dynamic-20260601T040113Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix8000To8999Test.php` as an additive real upstream date/affinity corpus shard.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`
- Upstream section: `atof-3.2`
- Scenario range: decimal REAL suffixes `8000..8999` from the recursive `format('18.44674407370955%04d',i+1)` query.
- Oracle: local `sqlite3 -batch :memory:` generates storage class, formatted REAL, GLOB guard, and quote/CAST round-trip parity rows for the exact 1000 suffixes.

Focused coverage:

- `1003` distinct TestRunner PASS cases.
- `14022` behavior assertions in the focused test file.
- Exercises `SQLiteSelectSql` CAST/function/GLOB dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL cast/storage affinity, and `SQLiteCoreScalarFunction` `format()`/`quote()` parity.

Non-overlap:

- Owns only `atof1.test` `atof-3.2` suffixes `8000..8999`.
- Avoids accepted `atof-3.2` suffixes `0000..4999` and `6000..7999`, the pending-ready `5000..5999` suffix shard, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, accepted `atof-3.3` exponent rows, `atof2` rounding, date4/date/timediff matrices, and types storage-class batches.

Dependency closure:

- No new support component needed.
- Reuses hydrated upstream `atof1.test`, local sqlite3 oracle parity, and existing native PHP CAST/GLOB/format/quote/REAL-affinity helpers.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix8000To8999Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix8000To8999Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
