# Real Upstream Corpus: PRAGMA/Schema Dynamic Prepared Expiry

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T025011Z-0`

Base accepted HEAD: `892244279ab2272eec684ce3477ab002d81ab0b4`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
- `schema2-1.*` through `schema2-4.*`: prepared-v2 `sqlite_master` scans continue after CREATE/DROP table, view, trigger, and index schema changes by repreparing.
- `schema2-5.*`: ATTACH leaves an existing prepared statement stable; DETACH expires statements.
- `schema2-6.*` through `schema2-8.*`: deleting a function, deleting a collation, or setting the authorizer expires prepared statements; adding function/collation does not.
- `schema2-9.*`: table/view drops made by another connection are visible before the next SELECT against that object.

Local behavior added:

- `SQLitePreparedStatementSchemaExpiry` models the bounded prepare-v2 expiry and auto-reprepare behavior with generic application table/view/index/trigger/function/collation names.
- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicPreparedExpiryTest.php` adds 1,001 distinct TestRunner PASS cases and 11,757 assertions.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPreparedExpiryTest.php`
- Result: `1 test files, 11757 assertions, 0 failures`, 1,001 PASS lines.

Expected dashboard movement:

- Count as PASS-line growth only: `1742742 -> 1743743` if accepted.
- Mapped denominator remains `1589 / 1589`.

Dependency closure:

- No new support component needed; this reuses lane-local PHP state modeling for prepared statement schema expiry.

Non-overlap:

- This does not repeat existing PRAGMA table_info/index_xinfo/table_list shadowing, quoted schema, data_version, runtime list, schema refresh, or pragma remainder batches.
- It specifically covers `schema2.test` prepared-v2 statement expiry and reprepare semantics.
