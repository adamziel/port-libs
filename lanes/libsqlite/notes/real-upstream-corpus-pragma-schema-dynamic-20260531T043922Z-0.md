# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T043922Z-0`

Base accepted HEAD: `0b81729d69877023d4b2607c8a1ffc5fac25bee0`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.*.1` through `schema3-1.*.22`

## Behavior Ported

- Added a focused dynamic corpus for the upstream multi-client schema-cache refresh matrix.
- Each case models connection 1 changing `sqlite_schema` with `CREATE TABLE`, `CREATE VIEW`, `CREATE INDEX`, `CREATE TRIGGER`, `ALTER TABLE ADD COLUMN`, or `DROP IF EXISTS`/recreate forms.
- The second connection is represented by a cached prepared statement with the old schema cookie. The test proves the statement is invalidated and the next PRAGMA schema-catalog lookup sees the new table, view, index, trigger, or added column shape.
- The dynamic matrix uses generic `existing_t*`, `fresh_t*`, and `fresh_i*` names only.

## Evidence

- Focused verification: `1101` TestRunner PASS cases and `6505` assertions in `SQLiteRealUpstreamPragmaSchemaDynamicSchema3CacheTest.php`.
- Non-overlap: this avoids accepted PRAGMA table-info/index-xinfo/schema-version/data-version, schema invalidation create/drop, schema4 object-name collision, schema6 equivalence, PRAGMA drop-matrix, and pragma6 integrity-check slices. The new surface is specifically `schema3.test` multi-client schema-cache refresh before the second connection executes a dependent statement.
- Dependency closure: no new support component is needed; this reuses lane-local `SQLiteSchemaDdlReparsePlan`, schema cookies, prepared-statement invalidation, and PRAGMA schema-catalog helpers.
