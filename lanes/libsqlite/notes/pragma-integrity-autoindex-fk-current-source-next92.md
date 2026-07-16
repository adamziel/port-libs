# pragma-integrity-autoindex-fk-current-source-next92

This slice extends `SQLitePragmaAutoindexForeignKeyPreflight` with
schema-aware current-source planning across temp/main/attached records.

Behavior:

- `planCurrentSource()` runs existing autoindex/FK parent-key preflight per
  schema and preserves the schema on every autoindex and foreign-key row.
- Empty schemas are skipped, while schemas with only FK metadata stay
  countable as blocked current-source rows.
- Blocking reasons are current-source specific:
  `autoindex_catalog_current_source` and
  `foreign_key_parent_autoindex_current_source`.
- The Application smoke covers copied `wp_options` temp/main/archive schemas
  where the same table names require per-schema autoindex/FK parent coverage.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityAutoindexFkCurrentSourceNext92Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
```

PASS-line delta: 65 new focused PHP PASS cases. `lane-status.json` `phpPass`
moves from 35916 to 35981. Mapped upstream coverage is unchanged because this
is focused PHP current-source behavior coverage, not a new upstream inventory
unit.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-integrity-autoindex-fk-current-source-next92.php --self-test
application-pragma-integrity-autoindex-fk-current-source-next92 self-test passed
```

Dependency closure: no new support component is needed. This reuses existing
`SQLitePragmaAutoindexForeignKeyPreflight`, `SQLitePragmaSchemaCatalog`,
`SQLiteCreateTable`, and `SQLiteSchemaRecord` primitives.

Non-overlap: avoids accepted batch89 PRAGMA integrity autoindex pointer-map
checks, batch86 table-valued FK checks, batch82 schema-qualified FK
pagination, and the older single-schema autoindex/FK preflight. The new
surface is schema-tagged current-source aggregation across temp/main/attached
schemas before the next import write.
