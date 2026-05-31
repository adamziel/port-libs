# real-upstream-corpus-pragma-schema-dynamic-20260531T040309Z-0

Added `SQLiteRealUpstreamPragmaPageCountDynamicTest.php` as an additive real upstream PRAGMA schema dynamic corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Sections: `pragma-14.1`, `pragma-14.2`, `pragma-14.2uc`, `pragma-14.3`, `pragma-14.3uc`, `pragma-14.4`, `pragma-14.5`, `pragma-14.6`, and `pragma-14.6uc`.

Behavior covered:

- `PRAGMA page_count` and `PRAGMA main.page_count` return the same main-schema page count.
- `PRAGMA temp.page_count` and attached-schema `PRAGMA aux.page_count` read independent schema-local page counts.
- Case-insensitive `PAGE_COUNT` and uppercase schema names resolve to the same pragma/schema state.
- Transaction-local page-count growth is visible before rollback and the prior page count is visible after rollback.
- Assignment forms are accepted as read-only no-ops, preserving the current page count.

Focused coverage:

- Added 1,000 dynamic TestRunner PASS cases plus one source-citation case.
- Each dynamic case asserts empty main, created main/temp, transaction growth, rollback restoration, attached schema, uppercase pragma/schema resolution, read-only assignment no-op, and page-count dependency provenance.

Non-overlap:

- This does not repeat prior `pragma2.test` `cache_spill` coverage, `pragma2.test` `freelist_count` coverage, `pragma.test` cache/default-cache/schema-version coverage, `pragma4/pragma5` table-valued schema catalog coverage, or PRAGMA fault/integrity coverage.
- The new file owns only the real upstream `pragma.test` `pragma-14.*` `page_count` cluster.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local `SQLitePragmaDynamicSchemaState` page-count primitive.
