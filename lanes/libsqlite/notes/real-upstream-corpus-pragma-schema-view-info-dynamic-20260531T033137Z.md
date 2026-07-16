real-upstream-corpus-pragma-schema-dynamic-20260531T033137Z-0

Scope:
- Ported real upstream SQLite PRAGMA/view schema behavior from `test/view.test`
  `view-1.11` through `view-1.14`, plus schema reparse behavior from
  `test/pragma4.test` `4.1.*` and `4.2.*`.
- Added bounded `PRAGMA table_info(view)` inference for simple views over one
  source table, including direct column projection, `SELECT *` expansion,
  schema-qualified table-valued PRAGMA calls, explicit view column aliases, and
  dropped-view reparse invalidation.

Focused movement:
- New PHP focused test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaViewInfoDynamicTest.php`
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaViewInfoDynamicTest.php`
- Result: `1 test files, 17005 assertions, 0 failures`
- PASS-line delta: `3001` distinct TestRunner PASS cases.

Non-overlap:
- Avoids the accepted PRAGMA shadowing/defaults/index_xinfo/foreign_key_list
  coverage in `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` by
  targeting view column inference for `PRAGMA table_info(view)` and view
  reparse/drop behavior.
- Adds no domain-specific API names or source text.

Dependency closure:
- No new support component is needed. This reuses the existing bounded
  `SQLitePragmaSchemaCatalog`, `SQLiteAttachedSchemaCatalog`, and schema-record
  model.

Next:
- Broaden view inference only when backed by additional real upstream view or
  PRAGMA cases, such as compound view projections or expression aliases.
