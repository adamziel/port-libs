# real-upstream-corpus-date-affinity-dynamic-20260531T091755Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test`.
- Ported sections: `types-1.1.*` (`INSERT INTO <table> VALUES(...)`), `types-1.2.*` (`INSERT INTO t1 SELECT ...`), and `types-1.3.*` (`UPDATE <table> SET ...`).
- Behavior fixed: insert/update affinity now stores exact REAL values such as `5.0` as `integer` in INTEGER and NUMERIC affinity columns while preserving BLOB values as `blob`.
- Focused coverage: `SQLiteRealUpstreamCorpusDateAffinityDynamicTypesStorage20260531T091755ZTest.php` adds 1084 real upstream TestRunner PASS cases and 12039 assertions.
- Non-overlap: avoids fully exhausted `date4.test`, existing date/date2/date3/date5/timediff files, accepted `affinity2.test` and `affinity3.test` expression-affinity batches, and existing `types2.test`/`types3.test` text-affinity dynamic batches. This owns `types.test` storage-affinity statement forms `types-1.1` through `types-1.3`.
- Status movement: lane-local `phpPass` moves from 2835919 to 2837003 if accepted; mapped coverage remains 1589 / 1589 because `types.test` is already mapped.
- Dependency closure: no new support component is needed. The patch reuses `SQLiteRealExpressionAffinityCorpusPlan`, `SQLiteAffinityComparison`, and `SQLiteBlobValue`.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTypesStorage20260531T091755ZTest.php` -> `1 test files, 12039 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTypesStorage20260531T091755ZTest.php lanes/libsqlite/tests/SQLiteAffinityComparisonStorageClassCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicSchemaTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `5 test files, 14480 assertions, 0 failures`.
  - `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTypesStorage20260531T091755ZTest.php` -> no syntax errors.
  - `php -r '$data=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status.json OK\n";'` -> `lane-status.json OK`.
  - `git diff --check -- lanes/libsqlite` -> passed.
