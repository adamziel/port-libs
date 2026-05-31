## real-upstream-corpus-date-affinity-dynamic-20260531T145127Z-0

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atof1.test`, upstream scenario `atof-3.2`.
- Added focused PHP corpus shard: `SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix2000To2999Test.php`.
- Owned upstream range: decimal REAL suffixes `2000..2999` from the upstream recursive `format('18.44674407370955%04d',i+1)` matrix.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicAtof1DecimalRealSuffix2000To2999Test.php` passed with `1 test files, 14022 assertions, 0 failures`.
- PASS-line delta: `+1003` focused TestRunner PASS cases: source-truth gate, `1000` per-suffix behavior cases, generic application rollup, and non-overlap/dependency-closure gate.
- Non-overlap: avoids accepted `atof-3.2` suffixes `0000..1999`, accepted `atof-3.1` integer-prefix suffixes `0592..1609`, `atof2` rounding rows, date4/date/time matrices, and types storage-class batches.
- Dependency closure: no new support component needed; the shard reuses `SQLiteSelectSql` CAST/GLOB/function dispatch, `SQLiteRealExpressionAffinityCorpusPlan` REAL affinity/storage-class helpers, `SQLiteCoreScalarFunction` quote/format behavior, and sqlite3 oracle parity.
- Guard evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files, 3 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.
