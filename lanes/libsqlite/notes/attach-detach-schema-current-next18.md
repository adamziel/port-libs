# Attach Detach Schema Current Next18

This slice adds bounded native PHP execution for SQL-form attached schema lifecycle statements:

- `ATTACH [DATABASE] <file> AS <schema>` now attaches a schema through `SQLiteAttachedSchemaCatalog::executeAttachDetachSql()`.
- `DETACH [DATABASE] <schema>` removes attached schemas and resequences `PRAGMA database_list` rows.
- The attach executor accepts bounded string-literal/path file expressions, quoted schema identifiers, and an optional file/schema record loader.
- Current-source lookup and schema PRAGMAs update immediately after attach/detach, preserving `temp`, `main`, then attached search order.
- The Application smoke demonstrates copied `wp_options` temp shadowing while a site metadata database is attached, introspected, detached, and removed from current-source PRAGMA lookup.

## Focused Verification

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS attach detach schema current next18 attaches SQL database with loaded records
PASS attach detach schema current next18 database list includes SQL attached schema
PASS attach detach schema current next18 unqualified lookup sees attached table after main temp miss
PASS attach detach schema current next18 temp still shadows attached table after attach SQL
PASS attach detach schema current next18 attach order controls unqualified attached conflicts
PASS attach detach schema current next18 detach SQL removes current attached winner
PASS attach detach schema current next18 detach SQL resequences pragma database list
PASS attach detach schema current next18 pragma database list reflects SQL attach detach
PASS attach detach schema current next18 schema qualified pragma uses attached current record
PASS attach detach schema current next18 unqualified pragma resolves attached current table
PASS attach detach schema current next18 unqualified index pragma resolves attached current index
PASS attach detach schema current next18 detach removes attached pragma current source
PASS attach detach schema current next18 double quoted schema identifier is normalized
PASS attach detach schema current next18 bracket quoted schema identifier is normalized
PASS attach detach schema current next18 backtick quoted detach identifier removes schema
PASS attach detach schema current next18 single quoted file expression unescapes apostrophes
PASS attach detach schema current next18 double quoted file expression unescapes quotes
PASS attach detach schema current next18 bare bounded path token attaches
PASS attach detach schema current next18 loader receives normalized file and schema
PASS attach detach schema current next18 default attach has empty schema records
PASS attach detach schema current next18 attached main table lookup remains explicit after SQL attach
PASS attach detach schema current next18 attached index lookup survives temp table shadowing
PASS attach detach schema current next18 sqlite schema alias remains main after SQL attach
PASS attach detach schema current next18 sqlite temp schema alias remains temp after SQL attach
PASS attach detach schema current next18 detach preserves main and temp database list heads
PASS attach detach schema current next18 reattach schema after detach uses new file
PASS attach detach schema current next18 duplicate SQL attach raises
PASS attach detach schema current next18 SQL attach rejects main schema
PASS attach detach schema current next18 SQL attach rejects temp schema
PASS attach detach schema current next18 SQL detach rejects main schema
PASS attach detach schema current next18 SQL detach rejects temp schema
PASS attach detach schema current next18 SQL detach rejects missing schema
PASS attach detach schema current next18 SQL attach rejects empty schema name
PASS attach detach schema current next18 SQL attach rejects empty file expression
PASS attach detach schema current next18 SQL attach rejects unbounded file expression
PASS attach detach schema current next18 SQL executor rejects non attach detach statement
PASS attach detach schema current next18 SQL attach accepts trailing semicolon whitespace
PASS attach detach schema current next18 SQL detach accepts trailing semicolon whitespace
PASS attach detach schema current next18 attach SQL preserves case folded schema in list
PASS attach detach schema current next18 detach current source falls through to later attachment

1 test files, 80 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `6121 -> 6161` from 40 newly verified focused PASS lines.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior for an existing attach/schema surface, not a newly hydrated upstream Tcl inventory unit.

## Non-Overlap

This avoids accepted attach/temp schema direct catalog coverage by adding SQL-form attach/detach execution and current-source rebasing after lifecycle statements. It also avoids accepted JSON table source/cursor/constraint work, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte truncation, B-tree page move/root collapse/overflow release, Unicode GLOB, and PRAGMA schema catalog row-cursor work.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local attached schema catalog, schema records, and PRAGMA catalog primitives.

## Next

Wire SQL-form attach/detach into a broader parser/executor transaction path once the native connection lifecycle has file-handle ownership for attached database images.

## 2026-05-30 Current-Base Parser Guard

This follow-up tightens the SQL-form ATTACH parser so an unquoted schema name cannot absorb trailing SQL tokens as part of the schema name. Quoted schema identifiers still normalize through the existing identifier path, and quoted file expressions may contain `as` text without changing the `AS <schema>` split.

Red-first reproduction before the fix:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
FAIL attach detach schema current next18 SQL attach rejects trailing schema tokens (lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php)
Expected exception InvalidArgumentException was not thrown
1 test files, 86 assertions, 1 failures
```

Focused verification after the fix:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 86 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCacheCurrentNext29Test.php lanes/libsqlite/tests/SQLiteAttachDetachTransactionCurrentTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 212 assertions, 0 failures

$ php lanes/libsqlite/examples/application-attach-detach-schema-current-next18.php
Application smoke printed attached site/archive database-list and detach results without error.

$ php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php && php -l lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php

$ git diff --check -- lanes/libsqlite
No output.
```

Dependency closure remains unchanged: no new support component is needed; this reuses the existing bounded ATTACH/DETACH SQL executor, schema catalog, URI/open planning, and PRAGMA current-source resolution.
