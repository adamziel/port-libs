# PRAGMA index_xinfo / foreign-key current-source next236

Slice: `pragma-index-xinfo-foreignkey-current-source-next236`.

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, an
additive current-source PRAGMA helper layered on accepted `index_xinfo`,
`foreign_key_list`, exact-arity, collation, partial-parent, and expression
UNIQUE parent-key diagnostics.

Behavior covered:

- derives parent UNIQUE index key columns from `PRAGMA index_xinfo`;
- compares them with `PRAGMA foreign_key_list` parent columns using SQLite
  identifier case-folding, while preserving exact quoted names from the schema;
- reports `exact_name_match`, `casefold_name_match`, and
  `missing_parent_unique_index` rows for current and next sources;
- includes the quoted/case-fold row summaries in the current-source cursor so
  paged resumes reject stale schema changes;
- preserves inherited `index_xinfo`, `foreign_key_list`, parent expression
  UNIQUE, exact arity, collation, and pagination behavior.

WordPress relevance:

Copied taxonomy/import schemas can retain mixed-case quoted identifiers from
export tools. SQLite still resolves parent-key columns case-insensitively, so
foreign-key repair diagnostics must not mark `"Slug"` / `"slug"` and
`"Term_ID"` / `"term_id"` as missing parent keys when `PRAGMA index_xinfo`
exposes the mixed-case names.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next236.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 69 assertions, 0 failures`
  - `54` PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next236.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next236 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +54`; mapped upstream coverage unchanged.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, parent
UNIQUE key diagnostics, and current-source pagination helpers.

Non-overlap: avoids accepted next231 expression UNIQUE parent-key rejection,
next229 exact UNIQUE arity, next224 parent collation matching, next188/187
partial parent UNIQUE diagnostics, next175 `foreign_key_list` row extraction,
and accepted PRAGMA optimize/index_xinfo/table-info analysis. The new surface
is quoted identifier case-fold matching between FK parent columns and
`index_xinfo` key names.
