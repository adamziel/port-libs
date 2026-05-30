# real-upstream-corpus-pragma-schema-dynamic-20260530T174411Z-0

- Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
    - `pragma-6.1`: `PRAGMA database_list` reports `main`, `temp`, and attached schemas in sequence order.
    - `pragma-6.6.1` through `pragma-6.6.4`: unqualified `PRAGMA table_info(name)` resolves a TEMP table before `main`, while schema-qualified `temp.` and `main.` stay pinned.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
    - `schema3-1.*`: a second connection must refresh its schema cache after another connection changes schema contents.
- Focused PHP coverage: extended `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` from 298 to 658 selected PASS lines and from 2607 to 4767 assertions.
  - Added 120 dynamic database-list variants for attached schema sequence/file rows.
  - Added 120 dynamic temp/main/attached `table_info` shadowing variants.
  - Added 120 dynamic schema-cache generation invalidation variants that re-read `main.table_info` after schema replacement while preserving temp shadowing for unqualified lookup.
- Non-overlap: this extends the existing real PRAGMA/schema dynamic corpus without repeating the prior `pragma4.test` default-comment/table-valued join block or the accepted PRAGMA schema follow-up batch. It claims focused PHP PASS-line growth only, not mapped denominator growth.
- Dependency closure: no new support component is needed; this reuses the existing `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and schema-record parser.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` -> no syntax errors
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` -> `1 test files, 4767 assertions, 0 failures`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` -> passed
  - `git diff --check -- lanes/libsqlite` -> clean
