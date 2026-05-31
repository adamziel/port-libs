# real-upstream-corpus-pragma-schema-dynamic-20260531T045306Z-0

Added `SQLiteRealUpstreamPragmaSchema2ActiveRuntimeBusyTest.php` as an additive real upstream PRAGMA/schema dynamic corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
- Sections: `schema2-11.1` through `schema2-11.8`.

Behavior covered:

- Active legacy `sqlite3_prepare()` statements that have stepped to `SQLITE_ROW` keep runtime objects busy.
- Deleting or replacing a user function while such a statement is active returns `SQLITE_BUSY` and does not invalidate statements or advance the schema/runtime generation.
- Deleting or replacing a collation sequence has the same busy behavior while the legacy statement is active.
- Resetting or finalizing the active legacy statement releases the busy condition, after which the runtime object change succeeds and records the expected runtime invalidation reason.

Focused coverage:

- Added 1,000 dynamic TestRunner PASS cases plus one source-citation/dependency case.
- The cases use generic application table/function/collation names and exercise the existing `SQLitePreparedStatementSchemaExpiry` legacy-statement runtime-object model.

Non-overlap:

- This does not repeat accepted `schema2.test` prepared-v2 schema object expiry, attach/detach expiry, function/collation deletion reprepare, authorizer changes, cross-connection object drops, rollback-cookie handling, PRAGMA lock/status, or table/index/FK/schema metadata batches.
- The new surface is specifically `schema2.test` `schema2-11.*` active legacy runtime-object `SQLITE_BUSY` behavior.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLitePreparedStatementSchemaExpiry` model.
