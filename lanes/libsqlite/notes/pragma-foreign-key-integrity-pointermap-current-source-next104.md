# PRAGMA Foreign-Key Integrity Pointer-Map Current Source Next104

Date: 2026-05-28T05:02:33Z

This isolated slice adds `SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield`, a mixed current-source cursor for paging:

- `PRAGMA integrity_check` pointer-map blockers from auto-vacuum database images.
- statement and table-valued `foreign_key_check` rows scoped through an attached schema catalog.
- stale resume rejection when database bytes, schema rows, catalog records, integrity SQL, foreign-key SQL, table-valued mode, or requested offset changes.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceNext104Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

Related PRAGMA regression evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceNext104Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapForeignKeyCurrentSourceNext94Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityFkIndexCurrentSourceNext99Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoIntegrityCurrentSourceNext100Test.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 257 assertions, 0 failures
```

Expected dashboard movement after clean integration: `phpPass` +65, from 40110 to 40175. No mapped upstream denominator change is claimed.

Application smoke:

```text
php lanes/libsqlite/examples/application-pragma-foreign-key-integrity-pointermap-current-source-next104.php --self-test
application-pragma-foreign-key-integrity-pointermap-current-source-next104 self-test passed
```

Syntax and whitespace evidence:

```text
php -l lanes/libsqlite/src/SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceYield.php
php -l lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPointerMapCurrentSourceNext104Test.php
php -l lanes/libsqlite/examples/application-pragma-foreign-key-integrity-pointermap-current-source-next104.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/libsqlite
```

Non-overlap note: this avoids accepted batch100 `PRAGMA index_xinfo` integrity checks, earlier pagination-only foreign-key integrity cursors, and accepted pointer-map vacuum/apply B-tree clusters. The new behavior is the combined pointer-map + foreign-key current-source cursor and stale-source rejection surface.

Dependency closure: reuses existing native PHP support (`SQLiteDatabase`, `SQLitePragmaIntegrityCurrentNextYield`, `SQLitePragmaForeignKeyIntegrity`, and `SQLiteAttachedSchemaCatalog`). No new support component is needed.
