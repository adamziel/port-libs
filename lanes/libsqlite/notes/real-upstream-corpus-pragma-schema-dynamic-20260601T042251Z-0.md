# real-upstream-corpus-pragma-schema-dynamic-20260601T042251Z-0

- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-6.1` through `schema-6.4`: adding a user function leaves a legacy prepared statement usable; deleting it makes the next legacy step fail and finalize report `SQLITE_SCHEMA`.
  - `schema-7.1` through `schema-7.4`: adding a collation is stable; deleting it expires legacy prepared statements with the same step/finalize result.
  - `schema-8.1` through `schema-8.2`: installing an authorizer expires a legacy `sqlite_master` scan.

- Implementation:
  - `SQLitePreparedStatementSchemaExpiry::step()` now distinguishes legacy `sqlite3_prepare()` statements from prepare-v2 statements when a schema/runtime operation has expired them.
  - Expired legacy statements return `SQLITE_ERROR` on the next step and finalize as `SQLITE_SCHEMA`, matching upstream `schema.test`, while existing prepare-v2 statements continue to auto-reprepare.

- Focused PHP coverage:
  - Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicLegacyRuntime20260601Test.php`.
  - New focused dynamic corpus: 1002 TestRunner PASS cases, 8756 assertions, 0 failures.
  - Direct affected-family check: prepared-expiry, active-runtime, schema2 active-runtime busy, and runtime-definition dynamic tests passed together with 47173 assertions, 0 failures.

- Non-overlap:
  - This owns upstream `schema.test` legacy runtime-definition and authorizer invalidation (`schema-6.*`, `schema-7.*`, `schema-8.1/8.2`).
  - It does not repeat accepted `schema2.test` prepare-v2 reprepare behavior, `schema2-11.*` active-statement busy behavior, schema rollback-cookie, schema3 cache refresh, schema4 namespace, schema5 legacy constraints, schema6 equivalence, PRAGMA table/list metadata, WAL, VFS, JSON, B-tree, or SELECT clusters.

- Dependency closure:
  - No new support component is needed; the patch extends the existing lane-local prepared statement schema-expiry model.

- Exclusion:
  - `schema.test` `schema-8.11`/`schema-8.12` clear-authorizer behavior remains a separate bounded follow-up because the current model only has explicit `set_authorizer` and `set_authorizer_deny` operations.
