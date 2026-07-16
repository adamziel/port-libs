# pragma-foreignkey-rootpage-integrity-current-source-next

This slice adds a combined current/next PRAGMA preflight for copied Application
SQLite images that need both sqlite_schema rootpage integrity blockers and
`foreign_key_check` rows annotated with child/parent rootpage and pointer-map
state.

- `SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNext` composes the
  existing native rootpage integrity collector with the FK rootpage pointer-map
  collector under one current/next source hash.
- The cursor rejects stale current/next database bytes, schema rows, catalog
  rootpages, FK SQL, integrity SQL, and mismatched offsets.
- Application smoke:
  `php lanes/libsqlite/examples/application-pragma-foreignkey-rootpage-integrity-current-source-next.php --self-test`
  reports `application-pragma-foreignkey-rootpage-integrity-current-source-next self-test passed`.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRootpageIntegrityCurrentSourceNextTest.php
1 test files, 70 assertions, 0 failures
```

Non-overlap: this avoids accepted FK-rootpage next114/117/120/140 and
index-list/quickcheck/FK next138/141/143 surfaces. The new surface is the
single source-stable cursor that preserves rootpage-only integrity blockers
beside FK child/parent rootpage pointer-map rows.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP schema catalog, rootpage integrity, FK check, pointer-map,
and SQLite page fixture helpers.
