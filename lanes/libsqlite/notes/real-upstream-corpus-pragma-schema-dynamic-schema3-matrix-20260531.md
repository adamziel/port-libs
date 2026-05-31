# Real Upstream Corpus: PRAGMA/Schema Dynamic Schema3 Matrix

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T065144Z-0`

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
- Ported scenarios: `schema3-1.$tn.1` through `schema3-1.$tn.22`

Behavior added:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchema3MatrixTest.php`.
- Covers 22 upstream multiclient schema-cache refresh cases across 50 generic
  variants for 1,100 distinct TestRunner PASS cases.
- Exercises schema DDL reparse/cache invalidation for create/drop table,
  create/drop/recreate index, create/drop/recreate trigger, create view, and
  `ALTER TABLE ... ADD COLUMN` visibility through schema PRAGMA rowsets.
- Uses generic `schema3_*` fixture names only; no WordPress-specific API or
  scenario surface was added.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema3MatrixTest.php`
  - Result: `1 test files, 7256 assertions, 0 failures`
  - PASS lines: `1101`

Non-overlap:

- This ports the real upstream `schema3.test` multiclient schema-refresh matrix
  rather than the already accepted pragma version, table-valued PRAGMA, temp
  store, schema invalidation batch, schema4 naming, schema5, schema6, or wide
  PRAGMA batches.

Dependency closure:

- No new support component is needed. The slice reuses
  `SQLiteAttachedSchemaCatalog` and `SQLiteSchemaDdlReparsePlan`.

Root harness:

- Not run; isolated micro-slice.
