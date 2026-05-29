# PRAGMA Foreign-Key Index Root Current-Source Next125

This slice adds `SQLitePragmaForeignKeyIndexRootCurrentSourceNext`, a
current-source paged PRAGMA rowset that combines `index_xinfo` metadata,
index-root integrity diagnostics, and foreign-key rootpage/pointer-map
diagnostics behind one stable source cursor.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIndexRootCurrentSourceNextTest.php`
- Result: `1 test files, 77 assertions, 0 failures`

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-foreignkey-index-root-current-source-next.php`
- Result: JSON summary for copied `wp_options`, `wp_option_names`,
  `wp_terms`, and `wp_term_taxonomy` schema checks, including index metadata,
  root integrity, FK rootpage rows, and pointer-map blocking reasons.

Non-overlap:

- Avoids accepted next121 `SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield`
  and next122 `SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext`
  behavior by leaving both source helpers unchanged and adding only the
  combined current-source cursor needed to page index metadata, index root
  diagnostics, and FK rootpage pointer-map diagnostics together.
- Avoids accepted PRAGMA FK/root/index/rootpage pointer-map checks from
  batch120/121/next122 by adding fresh cursor source invalidation for combined
  index SQL, database bytes, schema rows, and catalog changes.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded
  schema-catalog, index_xinfo, rootpage integrity, pointer-map, and
  foreign_key_check helpers.
