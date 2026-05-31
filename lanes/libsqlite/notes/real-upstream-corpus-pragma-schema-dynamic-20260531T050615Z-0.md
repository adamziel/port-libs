# real-upstream-corpus-pragma-schema-dynamic-20260531T050615Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schemafault.test`

Ported behavior:

- `schemafault.test` `schemafault-1.0` creates `CREATE VIEW v2(xxx, yyy) AS SELECT aaa, aaa+1 FROM t2` and repeatedly selects from the view during OOM fault simulation.
- The focused PHP port covers the schema behavior behind that upstream case: explicit view column lists remain the schema-visible output names even when a SELECT projection expression such as `aaa+1`, `aaa * 2`, `coalesce(aaa,0)`, concatenation, or `abs(aaa)` has no direct column name, while direct source-column projections keep their source declared type.

Implementation:

- `SQLitePragmaSchemaCatalog::columnsFromCreateView()` now uses the explicit view alias at the matching projection offset before rejecting unnamed expression projections.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicSchemaFaultViewAliasTest.php` with 1000 distinct TestRunner cases and 12001 focused assertions.

Non-overlap:

- This does not repeat existing `pragma.test` table-info/default/primary-key ordinal coverage, `pragma3.test` data-version coverage, `pragma4.test`/`pragma5.test` table-valued PRAGMA and table-list coverage, `schema5.test` legacy table constraints, `schema6.test` rowid/equivalence coverage, or accepted schema invalidation/cache-refresh batches. It targets `schemafault.test` explicit view aliases over expression projections.

Dependency closure:

- No new support component is needed. The patch reuses `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord`.
