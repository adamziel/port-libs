# real-upstream-corpus-pragma-schema-dynamic-20260531T062351Z-0

- Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
    `pragma-6.2`, `pragma-6.2.2`, `pragma-6.2.3`, `pragma-6.3`,
    `pragma-6.4`, `pragma-6.5.1`, `pragma-6.5.1b`, `pragma-6.5.1c`,
    `pragma-6.6.1` through `pragma-6.6.4`, `pragma-6.7`, `pragma-6.8`,
    and `pragma-7.1.1` through `pragma-7.1.2`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
    `schema-10.1` through `schema-10.5` and `schema-11.1` through
    `schema-11.8`.
- Added focused PHP test:
  - `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicNamespaceDefaults20260531Test.php`
- Behavior covered:
  - Dynamic `sqlite_schema` SQL text drives `PRAGMA table_info`,
    `pragma_table_info`, `PRAGMA foreign_key_list`, `PRAGMA index_list`,
    `PRAGMA index_info`, `PRAGMA index_xinfo`, and `pragma_table_list`
    rowsets.
  - DEFAULT text, declared type text, NOT NULL flags, duplicate
    primary-key ordinals, temp/main schema qualification, autoindex origins,
    partial indexes, DESC/collation metadata, and missing-target empty rowsets
    remain stable across 200 source-neutral namespace variants.
  - The schema active-reader model admits CREATE TABLE while preserving
    schema rows and blocks function/collation deletion or replacement with
    `SQLITE_BUSY` while a statement is active.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicNamespaceDefaults20260531Test.php`
  - Result: `1 test files, 8606 assertions, 0 failures`.
  - PASS-line growth: `1002` distinct focused TestRunner PASS cases.
- Non-overlap:
  - Avoids already accepted PRAGMA schema5/schema6/result-shape,
    join-xinfo, prepared-expiry, page-count, object-name-collision, visible
    table-valued list, schema version reload, and generated-column declared
    name batches by focusing on namespace/default/active-reader behavior from
    `pragma.test` and `schema.test`.
- Dependency closure:
  - No new support component needed; this reuses lane-local
    `SQLitePragmaSchemaCatalog` rowset behavior plus a bounded active-statement
    state model.
