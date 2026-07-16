# real-upstream-corpus-pragma-schema-dynamic-20260531T022730Z-0

Scope: extended the existing real upstream PRAGMA/schema dynamic shadowing corpus in `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` from variants 1-250 to variants 1-400.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test` sections 4.* through 7.* for schema-qualified table-valued PRAGMA resolution.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test` sections 1.0 through 3.1 for `pragma_table_list()` row shape and flags.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test` sections schema-4.*, schema-9.*, and schema-10.* for schema-cache invalidation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test` sections 6.2.* and 6.5.* for table-info defaults, primary-key ordinals, index metadata, and virtual PRAGMA metadata.

Focused movement:

- Before focused run: 2,001 PASS cases, 17,755 assertions, 0 failures.
- After focused run: 3,201 PASS cases, 28,405 assertions, 0 failures.
- Expected non-overlapping growth: +1,200 focused TestRunner PASS cases over real upstream PRAGMA/schema behaviors.

Dependency closure: no new support component is needed. This reuses the existing `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, `SQLitePragmaRowCursor`, and `SQLiteSchemaRecord` bounded native PHP components.

Non-overlap: this stays in PRAGMA/schema dynamic shadowing and metadata behavior. It does not touch accepted WAL/VFS/B-tree/JSON/SELECT executor clusters, does not add domain-specific APIs, and does not change mapped-denominator metadata.
