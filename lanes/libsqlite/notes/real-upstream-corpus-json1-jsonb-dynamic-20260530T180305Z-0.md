# real-upstream-corpus-json1-jsonb-dynamic-20260530T180305Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
  - Ported sections: `json101-1.1.00` through `json101-1.4b`, `json101-2.1` through `json101-2.5`, `json101-3.1` through `json101-3.4b`, `json101-4.1` through `json101-4.8`, `json101-5.2`, and `json101-6.1` through `json101-6.7`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
  - Ported sections: `json102-100` through `json102-320`, focused on constructors, JSON/JSONB argument provenance, `json_array_length`, `json_extract/jsonb_extract`, and mutation no-op behavior.

## Behavior Delta

- Added `SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php` with 112 focused `TestRunner` PASS cases.
- Fixed `SQLiteJsonMutation::mutateSqlFunctionArguments()` so `json_replace(JSON)`, `json_set(JSON)`, `json_insert(JSON)`, and their JSONB forms accept no edit pairs and return the input unchanged, matching upstream `json101-4.6` through `json101-4.8`.
- This is non-overlapping with accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, and prior JSON mutation path corpus coverage because it ports the real upstream constructor/extract/no-edit dynamic rows from `json101.test` and `json102.test`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php`
  - `1 test files, 112 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathMutationCorpusTest.php lanes/libsqlite/tests/SQLiteJsonScalarRegressionCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php`
  - `3 test files, 235 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonMutation.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON, JSONB, JSON5, path, inspection, extract, remove, and mutation helpers.
