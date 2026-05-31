# real-upstream-corpus-pragma-schema-dynamic-20260531T011217Z-0

Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`.

Added `SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php`, a real upstream
PRAGMA/schema dynamic corpus sourced from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-8.1.1` through `pragma-8.1.18`: schema_version assignment,
  DEFENSIVE rejection, DDL cookie movement, attached schema cookies, and stale
  prepared statement expiry.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  `pragma-8.2.1` through `pragma-8.2.15`: user_version independence,
  attached-schema isolation, transaction rollback restoration, and signed
  values.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  `pragma3-100` through `pragma3-340`: data_version read-only semantics,
  local-connection values, local commit non-movement, and external commit
  movement.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  `schema-4.*`: rolled-back schema changes can restore an equal cookie while
  statements prepared during the rolled-back schema still expire.

Focused movement:

- New focused TestRunner PASS cases: `1201`.
- New focused behavior assertions: `11705`.
- Expected selected PASS movement if accepted: `1436524 -> 1437725`.
- Mapped denominator movement: none; upstream inventory is already mapped.

Non-overlap:

- Does not repeat the accepted/generated PRAGMA xinfo, pragmafault integrity,
  table-list shadowing, PRAGMA cache_spill, generated-column xinfo, corrupt
  view, or schema/table-info dynamic corpus files. This slice targets the
  schema/data/user version and schema-rollback prepared-statement expiry
  cluster.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  native PHP `SQLitePragmaSchemaDataVersion` and
  `SQLitePragmaDataVersionTracker` primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php`
  - `No syntax errors detected`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php`
  - `1 test files, 11705 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaDataVersionCurrentNext25Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php`
  - `3 test files, 20273 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
