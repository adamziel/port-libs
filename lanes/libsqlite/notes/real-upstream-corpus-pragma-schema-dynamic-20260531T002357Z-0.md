# real-upstream-corpus-pragma-schema-dynamic-20260531T002357Z-0

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
- Ported sections: `schema4-1.1` through `schema4-1.6`, `schema4-2.1` through `schema4-2.5`, and `schema4-2.6` through `schema4-2.11`.

Behavior added:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicNameCollisionTest.php` with 1,000 dynamic variants plus a source-citation case.
- Covers SQLite's rule that triggers can share names with tables, views, and indexes in the same schema.
- Verifies dropping a same-named table/view/index preserves triggers that target another table.
- Verifies `ALTER TABLE ... RENAME TO ...` rewrites the renamed table and dependent index while preserving same-named triggers on their original target.
- Verifies a temp trigger and temp table can share a name, and schema-cache invalidation plus `PRAGMA table_list` still resolve the temp table.

Focused evidence:

- Red-first: initial focused run failed 1,000 cases due quote-style-specific rename assertions; assertions were corrected to require semantic rewritten object names without imposing storage quote formatting.
- Passing: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicNameCollisionTest.php`
- Result: `1 test files, 29004 assertions, 0 failures`, `4001` PASS lines.

Non-overlap:

- Distinct from existing `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` and `SQLiteRealUpstreamPragmaSchemaDynamicPragma4Test.php`; those cover table-valued PRAGMA resolution, attached-schema shadowing, and cursor stability.
- This slice targets `schema4.test` object-name collision and ALTER TABLE rename behavior around same-named triggers.

Dependency closure:

- No new support component required. This reuses existing `SQLiteSchemaDdlReparsePlan`, `SQLiteAttachedSchemaCatalog`, and `SQLitePragmaSchemaCatalog` behavior.
