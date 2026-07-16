# real-upstream-corpus-expression-affinity-dynamic-20260531T115530Z-0

## Source truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test`
- Ported scenarios:
  - `types-2.1.1` through `types-2.1.9`: INTEGER values and record payload sizes.
  - `types-2.2.1` through `types-2.2.3`: REAL values, including `0.0` stored with integer serial type 8 in non-legacy format.
  - `types-2.3.1` through `types-2.3.3`: NULL storage and two-byte record payload.
  - `types-2.4.1` through `types-2.4.3`: TEXT storage lengths 10, 500, and 500000 with UTF-8 payload sizes `{12 503 500004}`.
  - `types-2.5.1` through `types-2.5.3`: mixed NULL/TEXT/INTEGER row storage.

## Patch

- Added `SQLiteRecord::encodeWithColumnAffinities()` and `SQLiteRecord::parseWithColumnAffinities()` so record serialization can apply declared-column affinity and hide SQLite's REAL-affinity integer serial optimization on readback.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`, a focused dynamic corpus with 1001 distinct PASS cases. The first case pins exact upstream `types-2.*` payload-size expectations; the generated matrix expands the same storage-class rules over integer, real, text, and mixed records.
- Updated `lane-status.json` from `2902665` to `2903666` expected selected PASS cases for this pending handoff.

## Non-overlap

This does not repeat the already accepted `types-1.*` date/affinity insert/select/update storage-class matrix, trusted_schema result-shape coverage, e_select compound ORDER BY arm resolution, date/timediff4 roundtrip affinity, or prior expression scalar/operator coverage. This slice is limited to `types.test` record payload storage and column-affinity record encode/parse behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRecord.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRecord.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`
  - `1 test files, 4890 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecordSerialTypeCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`
  - `2 test files, 4974 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTypesStorage20260531T091755ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTypesRecordStorage20260531T115530ZTest.php`
  - `2 test files, 16929 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native `SQLiteRecord`, `SQLiteAffinityComparison`, and `SQLiteVarint` components and adds the missing column-affinity record entry points.

## Follow-up

Avoid repeating `types.test` `types-2.*` payload storage. The next expression/affinity batch should move to a different upstream section or to a behavior unblocker that increases selected PASS evidence without changing this record-storage surface.
