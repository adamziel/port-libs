# pragma-quickcheck-foreignkey-rootpage-current-source-next

Adds `SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext`, a bounded
current-source composer for Application import preflights that must page
expression-index metadata, shallow `PRAGMA quick_check` rootpage blockers, and
`PRAGMA foreign_key_check` rootpage/pointer-map rows behind one stable cursor.

Behavior covered:

- combines accepted index-rootpage quick_check rows with FK rootpage
  pointer-map rows without duplicating the standalone next122/next129 surfaces;
- reports combined readiness blockers in deterministic order:
  `quick_check`, `foreign_key_check`, `foreign_key_rootpage_catalog`,
  `rootpage_pointer_map`, and `rootpage_integrity`;
- keeps a source id over database bytes, schema catalog, FK schema data,
  index_xinfo SQL, quick_check SQL, and foreign_key_check SQL;
- rejects stale database, schema, quick_check SQL, FK SQL, and offset cursors;
- preserves target index/table metadata for copied `wp_options` expression
  indexes while FK rows may reference parent or taxonomy tables.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNextTest.php
```

Result: `1 test files, 80 assertions, 0 failures` with 70 focused PASS lines.

```sh
php lanes/libsqlite/examples/application-pragma-quickcheck-foreignkey-rootpage-current-source-next.php --self-test
```

Result: `application-pragma-quickcheck-foreignkey-rootpage-current-source-next self-test passed`.

Non-overlap:

This avoids accepted PRAGMA quickcheck/index_xinfo rootpage next129,
foreign-key rootpage pointer-map next122/next127, quickcheck/stat/FK next123,
index/integrity/FK current-source next121, and accepted PRAGMA integrity
rootpage diagnostics. The new behavior is the combined current-source cursor
and readiness gate across quick_check plus FK/rootpage rows.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local
schema catalog, PRAGMA quick_check, index_xinfo, and foreign_key_check
primitives.
