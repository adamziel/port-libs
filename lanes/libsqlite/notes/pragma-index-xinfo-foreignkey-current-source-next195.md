# pragma-index-xinfo-foreignkey-current-source-next195

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a
current-source PRAGMA helper layered on accepted `index_xinfo`,
`foreign_key_list`, parent-key, partial/expression/superset parent-index, and
child-index diagnostics. It detects parent UNIQUE indexes whose key columns are
the same set as the referenced parent key but appear in a different order.

SQLite parent-key admission is order-sensitive: `REFERENCES parent(a,b)` is not
satisfied by a UNIQUE index on `(b,a)`. The new rows keep this distinct from the
accepted next191 superset-prefix blocker and next190 expression-index blocker.

Behavior covered:

- appends `foreign_key_parent_permuted` rows after accepted FK/index_xinfo rows;
- distinguishes full `permuted_unique_only` blockers from partial permuted
  diagnostic rows;
- records expected FK column order, actual index order, per-term positions,
  current/next blocker counts, repair deltas, source hashing, and cursor paging;
- suppresses full permuted blockers once the next schema adds an exact ordered
  UNIQUE parent key;
- ignores superset and expression UNIQUE indexes so accepted next190/next191
  surfaces remain authoritative for those cases.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 63 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next195.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next195 self-test passed`

Non-overlap: this avoids accepted next191 parent-key superset diagnostics,
next190 expression parent-index diagnostics, next188 partial parent-index
admission, next186 child-index collation, next184 parent sort-order metadata,
next181 parent collation admission, and earlier FK/index_xinfo pagination
clusters. The new surface is exact parent-key order: a UNIQUE index on the same
columns in a different order does not satisfy the FK parent key.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `index_list`, `index_xinfo`, `foreign_key_list`, and
current-source pagination helpers.
