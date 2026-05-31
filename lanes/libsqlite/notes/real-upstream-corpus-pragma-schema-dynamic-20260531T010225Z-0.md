# real-upstream-corpus-pragma-schema-dynamic-20260531T010225Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/gencol1.test` `gencol1-21.1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test` `pragma-6.*`

Behavior ported:

- `pragma_table_xinfo()` reports generated columns in declaration order.
- Ordinary `PRAGMA table_info` hides generated columns while preserving original `cid` values.
- `table_list.ncol` counts generated and visible columns.
- A non-generated column declared as `INT Always default(5)` reports declared type `INT`; `ALWAYS` is not part of the type unless it introduces a generated `AS (...)` expression.

Focused movement:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicGeneratedXinfoTest.php` with 1,001 real upstream behavior PASS cases.
- Fixed `SQLitePragmaSchemaCatalog::declaredType()` so top-level `ALWAYS` ends the declared type.

Non-overlap:

- This does not repeat the existing PRAGMA schema shadowing, schema5/schema6 legacy-shape, schema/data-version, table-list shadowing, or pragmafault integrity batches. It targets generated-column `table_xinfo` metadata from `gencol1.test`.

Dependency closure:

- No new support component is needed. The existing schema catalog and PRAGMA table-info analysis components are reused.
