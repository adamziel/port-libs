# pragma-index-xinfo-foreignkey-current-source-next155

Slice: `pragma-index-xinfo-foreignkey-current-source-next155`.

This adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a bounded
current-source cursor that combines:

- explicit `PRAGMA index_xinfo(...)` rows for one selected index;
- scoped `PRAGMA foreign_key_list(table)` metadata for the table being copied;
- scoped `PRAGMA foreign_key_check(table)` violation rows for the same current
  source.

The cursor hashes the schema catalog, row-source schemas, normalized
`index_xinfo`, `foreign_key_list`, and `foreign_key_check` SQL, plus
table-valued PRAGMA mode flags. Paged resumes are rejected when those inputs or
the expected offset drift.

WordPress relevance: copied `wp_options` import/preflight code can page index
key/collation metadata together with FK declaration and violation rows before
choosing whether a copied options table is safe to promote.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next155.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next155.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 63 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses the existing
schema PRAGMA catalog, attached schema catalog resolution, and
`SQLitePragmaForeignKeyCheck` row evaluator.

Non-overlap: avoids accepted PRAGMA quickcheck/index/FK rootpage cursors,
table-level `index_list` enumeration, `index_xinfo` integrity/rootpage
pagination, and foreign-key rootpage integrity clusters. This slice only
materializes one selected `index_xinfo` stream beside FK declaration/check rows
under one source hash.
