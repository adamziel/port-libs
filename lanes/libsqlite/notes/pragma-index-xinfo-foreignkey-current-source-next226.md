# PRAGMA index_xinfo / foreign-key current-source next226

This slice adds a current-source PRAGMA diagnostic for foreign keys whose
`PRAGMA foreign_key_list` parent table is absent from the copied schema
catalog. Parent-key UNIQUE/index_xinfo admission cannot be trusted until the
referenced parent table itself resolves.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` wraps the accepted
  next219 parent-key permutation page.
- Appends `foreign_key_missing_parent_table` rows grouped from
  `PRAGMA foreign_key_list` output and the schema table catalog.
- Tracks current/next counts, repaired deltas, source hashes, pagination, and
  stale cursor rejection.
- Adds a Application taxonomy relationship smoke where the next copied catalog
  restores `wp_term_taxonomy` and `wp_network_terms` before FK repair proceeds.

Evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 57 assertions, 0 failures`
  - `47` focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next226.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next226 self-test passed`

Non-overlap:

- Avoids accepted next203 parent UNIQUE coverage, next206 rowid-alias parent
  coverage, next208/209 implicit parent-primary-key resolution, next217
  prefix/suffix/partial parent-key diagnostics, and next219 parent-key
  permutation checks.
- This slice only covers missing parent table catalog resolution before parent
  index metadata is considered.

Dependency closure:

- No new support component is needed. The implementation reuses
  `SQLitePragmaSchemaCatalog`, `PRAGMA foreign_key_list`, accepted
  `PRAGMA index_xinfo` page composition, and current-source cursor plumbing.
