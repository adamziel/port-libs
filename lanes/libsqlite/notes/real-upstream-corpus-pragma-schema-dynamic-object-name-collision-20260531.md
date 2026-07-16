# real-upstream-corpus-pragma-schema-dynamic object-name collision

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
- Upstream sections: `schema4-1.1` through `schema4-1.8` and `schema4-2.1` through `schema4-2.11`.

What this ports:

- Triggers may share names with a table, view, or index in the same schema.
- Dropping the namesake table/view/index object does not drop triggers whose target table is different.
- Recreating the namesake objects does not replace the existing trigger records.
- `ALTER TABLE ... RENAME TO ...` reparses dependent trigger targets and trigger SQL without confusing trigger names with table or index names.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicObjectNameCollisionTest.php`
- Result: `1 test files, 11404 assertions, 0 failures`
- PASS-line movement: `1501` new focused TestRunner cases.

Non-overlap:

- Does not repeat existing `pragma4.test` table-valued PRAGMA join coverage in `SQLiteRealUpstreamPragmaSchemaDynamicJoinMatrixTest.php`.
- Does not repeat `pragma4.test` / `pragma5.test` schema-qualified PRAGMA table/index/list metadata coverage in `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php`.
- Uses generic `settings_*` and `audit_log_*` schema names only.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteSchemaDdlReparsePlan` and `SQLiteSchemaRecord` implementation.
