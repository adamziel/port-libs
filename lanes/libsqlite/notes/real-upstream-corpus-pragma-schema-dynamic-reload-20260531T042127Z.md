# Real Upstream PRAGMA Schema Dynamic Reload Corpus

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T042127Z-0`

Accepted base: `5823f556f77d50bd49ce909acb22097fc44da229`

Added `SQLiteRealUpstreamPragmaSchemaDynamicReloadCorpusTest.php` with 1,000 dynamic behavior cases plus one source-citation case.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.1` through `pragma-6.8`: schema-query PRAGMAs, database_list order, temp/main qualification, defaults, composite primary-key ordinals, foreign keys, and index_info/index_xinfo.
  - `pragma-7.1.1` through `pragma-7.1.2` and `23.3` through `23.5`: schema-query PRAGMAs force schema reads/reloads and expose current index/table/foreign-key rows after DDL.
  - `pragma-11.*`: collation-list metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - `1.0` through `3.1`: PRAGMA virtual-table metadata for function/module/pragma lists.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/altertab.test`
  - `22.0` through `22.1`: explicit schema_version reload before later ALTER TABLE/default-column reads.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicReloadCorpusTest.php`
  - Result: `1 test files, 27005 assertions, 0 failures`
  - PASS-line growth: `+1001`

Status delta:

- `lanes/libsqlite/lane-status.json` `phpPass`: `2025275 -> 2026276`
- Mapped denominator unchanged at `1589 / 1589`.

Dependency closure:

- No new support component is needed. This uses existing `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and schema-cache invalidation helpers.

Non-overlap:

- This does not repeat the existing pragma shadowing, schema-version state, schema join corpus, view-info, page-count, or object-name-collision files. The new coverage targets reload visibility across schema-query PRAGMAs after catalog replacement, virtual PRAGMA metadata, and stable cursor rows.
