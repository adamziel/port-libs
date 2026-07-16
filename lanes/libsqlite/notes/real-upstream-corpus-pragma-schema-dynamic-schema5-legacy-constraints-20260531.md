# real-upstream-corpus-pragma-schema-dynamic-20260531T053840Z-0

Implemented a non-overlapping real upstream PRAGMA/schema dynamic batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`

Ported upstream sections:

- `schema5-1.1` through `schema5-1.2`: legacy adjacent `PRIMARY KEY(a) UNIQUE(a)` table constraints are accepted and enforce uniqueness.
- `schema5-1.3` through `schema5-1.4`: named `PRIMARY KEY`, `CHECK`, and `UNIQUE` table constraints without comma separators remain readable.
- `schema5-1.5` through `schema5-1.7`: separate `UNIQUE(a)` and composite `PRIMARY KEY(b,c)` table constraints produce distinct autoindex metadata and conflict diagnostics.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchema5LegacyConstraintsTest.php`.
- The file contributes `1001` focused TestRunner PASS cases: `1000` dynamic behavior cases plus one source/non-overlap/dependency citation case.
- Focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema5LegacyConstraintsTest.php`
- Result: `1 test files, 5755 assertions, 0 failures`.

Non-overlap:

- This targets upstream `schema5.test` legacy CREATE TABLE table-constraint syntax.
- It does not repeat accepted `pragma.test` table/index metadata, `pragma2` cache/freelist/runtime state, `pragma3` data-version, `pragma4`/`pragma5` table-valued PRAGMA rowsets, `pragma6` integrity/generated-schema behavior, `schema2` prepared-statement expiry, `schema4` object-name collision, `schema6` same-content equivalence, trusted-schema behavior, or metadata-only suite admission rows.
- It uses generic `legacy_settings_*` table names and adds no WordPress-specific source/API surface.

Dependency closure:

- No new support component is needed.
- The slice reuses `SQLiteCreateTable::automaticIndexColumnMetadata()` adjacent table-constraint parsing and `SQLitePragmaSchemaCatalog` autoindex/table-info introspection.
