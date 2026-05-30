### 2026-05-27 PRAGMA index_xinfo expression corpus next6

This isolated libsqlite slice adds bounded native PHP `PRAGMA index_xinfo`
support to `SQLitePragmaSchemaCatalog`. It covers expression-index terms,
ordinary indexed columns, collations, DESC flags, rowid auxiliary terms, and
WITHOUT ROWID primary-key auxiliary terms for copied Application schema
diagnostics. Expression terms report SQLite-style `cid = -2`, `name = null`,
and `key = 1`; rowid/primary-key auxiliary columns report `key = 0`.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteIndexXInfoExpressionCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

That focused file produced 56 PASS lines, moving `phpPass` from 2017 to 2073.

Additional focused regression check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteIndexXInfoExpressionCorpusTest.php
2 test files, 133 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pragma-index-xinfo-expression.php
```

The smoke prints copied `wp_options` expression-index `index_xinfo` rows with
key expression terms and an auxiliary rowid term, without requiring
`ext/sqlite`.

Non-overlap: this slice avoids accepted expression-index lookup/range-cost,
SQL expression ORDER BY, JSON table source/cursor/constraint, VFS writer/lock,
WAL rollback/checkpoint, B-tree page-move/root-collapse/overflow, and Unicode
GLOB clusters. It is limited to schema PRAGMA catalog behavior for
`index_xinfo`.

Dependency closure: no new shared support component is needed. The slice reuses
the existing lane-local schema catalog and pure PHP schema-record fixtures.
