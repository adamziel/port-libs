# real-upstream-corpus-pragma-schema-dynamic-20260530T221626Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T221626Z-0`

Base accepted HEAD: `661e026d244a8143c42a9b42e699177ff26e29f3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
  - `pragma6-1.0`: generated-column schema database opens from a hydrated image.
  - `pragma6-1.1`: temp `WITHOUT ROWID` table with primary key, unique defaults, and an oversized insert attempt does not poison later checks.
  - `pragma6-1.2`: `PRAGMA integrity_check` and `PRAGMA quick_check` both complete successfully.

Behavior ported:

- `SQLitePragmaSchemaCatalog::indexList()` now reports SQLite primary-key autoindexes with origin `pk`, while preserving `u` for UNIQUE autoindexes and `c` for created indexes.
- Added `SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php`, a generic dynamic corpus over generated columns, stored generated columns, temp-shaped `WITHOUT ROWID` primary-key/unique schemas, clean pointer-map leaf images, and both integrity PRAGMAs.
- The new corpus adds 1,000 distinct TestRunner PASS cases and 6,000 focused assertions.

Non-overlap:

- This does not repeat prior `pragma.test` table-info/table-xinfo/index-list coverage, `pragma3` data-version coverage, `pragma4` table-valued PRAGMA coverage, `pragma5` introspection runtime-list coverage, or the previous schema invalidation batches.
- The new behavior is specifically the upstream `pragma6.test` generated-column plus temp `WITHOUT ROWID` integrity/quick-check completion path and the coupled primary-key autoindex origin metadata needed by that path.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php`
  - `1 test files, 6000 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 26419 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragma6IntegrityGeneratedDynamicTest.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure:

- No new support component is needed. This reuses lane-local schema PRAGMA parsing, generated-column metadata, autoindex metadata, pointer-map page images, and `SQLitePragmaIntegrityCheck`.
