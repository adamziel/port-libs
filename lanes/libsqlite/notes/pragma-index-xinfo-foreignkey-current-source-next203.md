# pragma-index-xinfo-foreignkey-current-source-next203

This slice adds current-source parent-key coverage auditing across
`PRAGMA foreign_key_list`, `PRAGMA index_list`, and `PRAGMA index_xinfo`.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` wraps the accepted
  next196 combined `index_xinfo` / `foreign_key_list` page and appends
  deterministic `foreign_key_parent_coverage` rows.
- Each foreign-key group records child columns, parent columns, covering parent
  index name, index origin (`u` autoindex or `c` created index), key columns,
  collations, expression/partial metadata, and `covered` vs
  `missing_parent_unique` status.
- Current/next source IDs include the parent-coverage summary, so pagination
  cursors are rejected when copied schema reparses add or remove usable parent
  UNIQUE coverage.
- Application smoke coverage models a copied taxonomy metadata import whose
  current `wp_terms(slug)` UNIQUE key cannot cover `REFERENCES
  wp_terms(slug, locale)`, while the next schema repairs that FK parent key
  with `UNIQUE(slug, locale)`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 65 assertions, 0 failures`
  - `58` focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next203.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next203 self-test passed`

Non-overlap:

This avoids accepted next196 row-level `index_xinfo`/`foreign_key_list`
pagination, next193 parent UNIQUE column-order rejection, next192 parent
collation rejection, next190 expression-parent diagnostics, and accepted
rootpage/integrity PRAGMA checks. The new surface is positive/missing parent
UNIQUE coverage summarization for FK groups using `index_list` plus
`index_xinfo`.

Dependency closure:

No new support component is needed. The slice reuses the existing schema
catalog, `foreign_key_list`, `index_list`, `index_xinfo`, autoindex metadata,
and current/next pagination primitives.
