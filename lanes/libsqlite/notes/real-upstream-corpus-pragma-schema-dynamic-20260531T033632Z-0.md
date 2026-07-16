# real-upstream-corpus-pragma-schema-dynamic-20260531T033632Z-0

- Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
    - `pragma-6.7`: `PRAGMA table_info` preserves declared type text, NOT NULL flags, default literal/expression spelling, and pk ordinals.
    - `pragma-6.8`: duplicate names in a table-level `PRIMARY KEY(a,b,a,c)` consume ordinal positions without renumbering the first occurrence, so `c` reports `pk=4`.
- Focused PHP coverage: added `SQLiteRealUpstreamPragmaSchemaDynamicTableInfoDefaultsTest.php` with 901 focused PASS cases and 4503 assertions over dynamic table declarations.
- Non-overlap: this slice extends the accepted PRAGMA/schema corpus with table_info default/type and duplicate primary-key ordinal behavior only. It does not repeat PRAGMA runtime lists, cache_spill, pager settings, data_version, schema invalidation, JSON/VFS/WAL/B-tree, source-neutral cleanup, or metadata-only suite evidence.
- Dependency closure: no new support component is needed; this reuses the existing `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` helpers.
- Expected movement: PASS-line growth only, not mapped denominator growth.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTableInfoDefaultsTest.php` -> no syntax errors
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTableInfoDefaultsTest.php` -> `1 test files, 4503 assertions, 0 failures`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTableInfoDefaultsTest.php` -> `2 test files, 24854 assertions, 0 failures`
  - `SQLiteNoWordPressSpecificApiTest.php` was not present in this worktree.
