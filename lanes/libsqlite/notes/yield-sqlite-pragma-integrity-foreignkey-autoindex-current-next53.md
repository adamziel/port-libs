# yield-sqlite-pragma-integrity-foreignkey-autoindex-current-next53

## Behavior

- Adds `SQLitePragmaAutoindexForeignKeyPreflight` for current/next schema gates that combine PRAGMA `index_list`/`index_xinfo` autoindex metadata with foreign-key parent-key coverage.
- Reports generated `sqlite_autoindex_*` root pages, key columns, collations, `origin='u'`, uniqueness, and blocked states for missing roots, missing catalog rows, or collation mismatches.
- Treats INTEGER PRIMARY KEY parent columns as rowid-alias coverage so `PRAGMA foreign_key_check` parent validation does not require a separate autoindex.
- Adds a copied Application `wp_options` / `wp_option_names` smoke for checking that parent UNIQUE autoindexes are current before the next import write.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaAutoindexForeignKeyCurrentNext53Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 66 assertions, 0 failures
```

PASS-line delta: 65 new focused PHP PASS cases. `lane-status.json` `phpPass` moves from 19277 to 19342. `benchmarkDenominator.mapped` is unchanged because this is focused PHP behavior coverage, not a new upstream inventory unit.

## Non-Overlap

This avoids accepted batch49 PRAGMA freelist/foreign-key integrity diagnostics and does not repeat `SQLitePragmaIntegrityFreelistForeignKeyPreflight`, pointer-map/freelist integrity checks, or accepted `PRAGMA foreign_key_check` row violation counting. The new slice is schema-current autoindex metadata and parent-key readiness for the next write path.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLitePragmaSchemaCatalog`, `SQLiteCreateTable`, and `SQLiteSchemaRecord` primitives.
