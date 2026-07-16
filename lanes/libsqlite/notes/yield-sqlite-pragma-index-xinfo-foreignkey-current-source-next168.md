# PRAGMA index_xinfo + foreign_key current-source next168

## Behavior

- Preserved automatic UNIQUE/PRIMARY KEY index column collation and DESC
  metadata when `PRAGMA index_xinfo` reports autoindex rows.
- Added current/next coverage for a copied `wp_options` foreign key whose
  parent key is backed by a table-declared `UNIQUE(name COLLATE NOCASE,
  blog_id)` autoindex.
- The next-source page now admits the parent autoindex with `NOCASE`/`BINARY`
  collations, clears the remaining `foreign_key_check` row after parent repair,
  and rejects stale source/offset cursors.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext168Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 84 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next168.php --self-test
```

## Non-overlap

This avoids accepted next156/157/158/159/161/163/164 PRAGMA index_xinfo and
foreign-key current-source slices. Those covered paired source cursors,
implicit parent primary-key resolution, and casefolded table/column data. This
slice is narrower: autoindex `index_xinfo` collation metadata used by FK parent
unique-index admission.

## Dependency Closure

No new support component is needed. The patch reuses the native schema catalog,
automatic-index metadata parser, PRAGMA row cursor, and foreign-key integrity
collector.
