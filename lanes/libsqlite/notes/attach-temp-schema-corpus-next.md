# Attach Temp Schema Corpus Next

## Scope

- Added `SQLiteAttachedSchemaCatalog` for bounded SQLite `main` / `temp` / attached schema catalog behavior.
- Covered TEMP object shadowing, explicit schema qualification, attach-order unqualified lookup, `DETACH` resequencing, `PRAGMA database_list`-style rows, and per-schema `SQLitePragmaSchemaCatalog` wrapping.
- Added a Application smoke showing copied `wp_options` temp shadowing with explicit `main.wp_options` and attached site metadata resolution.

## Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS attach temp schema corpus resolves temp before main and attached databases
PASS attach temp schema corpus explicit schema bypasses temp shadowing
PASS attach temp schema corpus unqualified attached names follow attach order
PASS attach temp schema corpus database list preserves sqlite sequence rows
PASS attach temp schema corpus detach resequences attached database list
PASS attach temp schema corpus wraps schema pragma catalogs per database
PASS attach temp schema corpus validates attach and detach schema names
PASS attach temp schema corpus unqualified view resolves from main when temp lacks view
PASS attach temp schema corpus main view root page remains null
PASS attach temp schema corpus site table schema-qualified root
PASS attach temp schema corpus archive table schema-qualified root
PASS attach temp schema corpus bracket quoted schema name resolves
PASS attach temp schema corpus backtick quoted table name resolves
PASS attach temp schema corpus double quoted table name resolves
PASS attach temp schema corpus main index explicit root
PASS attach temp schema corpus temp index explicit root
PASS attach temp schema corpus site index explicit root
PASS attach temp schema corpus archive index explicit root
PASS attach temp schema corpus missing unqualified index returns null
PASS attach temp schema corpus main record count
PASS attach temp schema corpus temp record count
PASS attach temp schema corpus site record count
PASS attach temp schema corpus archive record count
PASS attach temp schema corpus missing explicit schema raises on table lookup
PASS attach temp schema corpus missing explicit schema raises on index lookup
PASS attach temp schema corpus missing schema records raises

1 test files, 59 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-attach-temp-schema-corpus.php
{
    "default_wp_options_schema": "temp",
    "default_wp_options_root": 4,
    "main_wp_options_root": 2,
    "site_meta_schema": "site",
    "database_list": [
        {"seq": 0, "name": "main", "file": null},
        {"seq": 1, "name": "temp", "file": ""},
        {"seq": 2, "name": "site", "file": "/srv/www/site-meta.sqlite"}
    ]
}
```

## Status Delta

- `phpPass`: `1336 -> 1362` from the verified `+26` focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP corpus coverage, not a new upstream inventory mapping.
- Dependency closure: no new support component needed; reuses existing lane-local schema record and PRAGMA catalog primitives.

## Non-Overlap

This avoids the accepted schema PRAGMA/DDL corpus by testing attached-database and TEMP name-resolution behavior, not header PRAGMAs, `locking_mode`, `CREATE TABLE` index inference, or parser-level JSON/SELECT/VFS/WAL/B-tree clusters.
