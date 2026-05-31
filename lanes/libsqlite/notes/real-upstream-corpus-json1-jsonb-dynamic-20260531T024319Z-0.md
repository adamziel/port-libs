# real-upstream-corpus-json1-jsonb-dynamic-20260531T024319Z-0

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- Sections ported:
  - `json101-10.*`: strict JSON string backslash escape admission/rejection.
  - `json501-14.*`: JSON5 raw control-character string acceptance and canonical JSON/JSONB output escaping.
- Added focused PHP coverage:
  - `lanes/libsqlite/tests/SQLiteRealUpstreamJsonStringValidityDynamicTest.php`
  - 1,083 distinct TestRunner cases: 90 strict escape cases, 992 JSON5 raw-control dynamic cases, and one upstream citation/count case.
- Non-overlap:
  - This does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window behavior, JSON105 reverse-index mutation, JSON106/108 invariant/pretty batches, JSON107 legacy BLOB text behavior, JSON109 array insertion, JSON501/502 object/path label cases, or JSONB remove/path mutation coverage.
  - The slice owns string-validity lexer behavior for backslash escapes and JSON5 raw controls.
- Dependency closure:
  - No new support component is needed. This reuses lane-local `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, `SQLiteJsonCanonical`, `SQLiteJsonExtract`, and `SQLiteJsonB` behavior against hydrated upstream SQLite test files.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJsonStringValidityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonStringValidityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
