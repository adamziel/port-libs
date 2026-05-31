# real-upstream-corpus-pragma-schema-dynamic-20260531T001200Z-0

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - Ported sections: `pragma4-6.1`, `pragma4-6.2`, and `pragma4-6.3`.
- Behavior cluster: `PRAGMA table_list` must continue returning catalog rows when a view definition has been made semantically corrupt by a writable-schema edit. The caller-facing result reports the malformed view row with `ncol=0` and ordinary table rows without leaking an internal schema-reparse/log error.
- Behavior fix: `SQLitePragmaSchemaCatalog::tableList()` now reports `ncol=0` for a view projection that contains an unresolved function, matching upstream `pragma4.test` corrupt-view tolerance instead of inferring a caller-visible column from an invalid view body.
- Focused PHP coverage: added `SQLiteRealUpstreamPragmaSchemaDynamicCorruptViewTest.php` with 1001 focused TestRunner PASS cases and 11004 assertions.
- Non-overlap: the earlier `real-upstream-corpus-pragma-schema-dynamic-20260530T162807Z-0` and related follow-up files cover table-info/index/FK row shapes, table-valued joins, schema-version runtime state, and name-collision DDL reparse behavior. This slice only covers corrupt-view tolerance during `PRAGMA table_list` enumeration from `pragma4.test` 6.1-6.3.
- Dependency closure: no new support component is needed; this reuses `SQLitePragmaSchemaCatalog` table-list enumeration and existing schema-record parsing.
- Verification:
  - `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorruptViewTest.php` passed.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorruptViewTest.php` passed with `1 test files / 11004 assertions / 0 failures / 1001 PASS lines`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicJoinMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` passed with `2 test files / 25856 assertions / 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files / 3 assertions / 0 failures`.
  - `git diff --check -- lanes/libsqlite` passed.
