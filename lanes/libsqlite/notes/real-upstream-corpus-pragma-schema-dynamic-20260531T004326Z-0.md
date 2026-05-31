# real-upstream-corpus-pragma-schema-dynamic-20260531T004326Z-0

Ported a focused upstream PRAGMA/schema dynamic cluster from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test` `schema-12.1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test` `pragma-8.*`

Behavior added:

- schema-changing DDL inside a transaction can advance the schema cookie;
- rollback restores the previous schema cookie;
- a later schema change may return the cookie to the same numeric value seen by a prepared statement;
- statements prepared during the rolled-back schema are still expired, because cookie equality alone is not sufficient after rollback;
- unrelated schema statements and current-cookie statements remain preserved.

Focused test growth:

- `SQLiteRealUpstreamPragmaSchemaDynamicRollbackCookieTest.php` adds 1000 distinct upstream-derived TestRunner cases plus one source-citation case.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP `SQLitePragmaSchemaDataVersion` state model.

Non-overlap:

- This does not repeat the accepted PRAGMA corrupt-view, schema shadowing, data_version matrix, schema3 DDL cache refresh, or schema4 namespace/name-collision slices. The new cluster targets the upstream `schema-12.1` rollback-cookie prepared-statement hazard.
