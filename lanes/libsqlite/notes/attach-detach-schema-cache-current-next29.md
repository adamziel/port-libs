# Attach Detach Schema Cache Current Next29

This slice adds bounded native PHP schema-cache generation behavior for
ATTACH/DETACH lifecycle changes:

- `SQLiteAttachedSchemaCatalog` now bumps a connection-level schema generation
  after successful ATTACH and DETACH statements.
- `schemaCacheSnapshot()` captures the current database list, search order,
  schema names, source schema, and generation.
- `schemaCacheIsCurrent()` and `schemaCacheInvalidation()` report stale
  prepared-plan state after attached databases are added, removed, resequenced,
  or detached and reattached under the same schema name.
- ATTACH/DETACH SQL results expose `schema_generation` and
  `cache_invalidated` diagnostics while preserving the existing
  database-list/open-plan behavior.
- The Application smoke demonstrates invalidating a copied wp_sitemeta metadata
  plan after DETACH so import code can reprepare stale schema PRAGMAs without
  ext/sqlite.

Verification:

```text
$ php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php

$ php -l lanes/libsqlite/tests/SQLiteAttachDetachSchemaCacheCurrentNext29Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachDetachSchemaCacheCurrentNext29Test.php

$ php -l lanes/libsqlite/examples/application-attach-detach-schema-cache.php
No syntax errors detected in lanes/libsqlite/examples/application-attach-detach-schema-cache.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCacheCurrentNext29Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 66 assertions, 0 failures
60 PASS lines

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCacheCurrentNext29Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 146 assertions, 0 failures

$ php lanes/libsqlite/examples/application-attach-detach-schema-cache.php
Outputs attachGeneration 1, attachInvalidatedSchemas ["site"],
sitePlanCurrentAfterDetach false, detachedSchemas ["site"], and an empty
wpSitemeta fallback rowset after DETACH.
```

Expected dashboard movement:

- `phpPass`: `10028 -> 10088` from 60 newly passing focused TestRunner cases.
- `benchmarkDenominator.mapped`: unchanged. This is focused PHP behavior
  coverage and does not claim a newly mapped upstream inventory unit.

Non-overlap:

- Avoids accepted ATTACH temp/VFS open planning, attached temp view/trigger/FK
  resolution, PRAGMA schema/data version state, PRAGMA catalog rebasing,
  schema shadowing, open URI planning, and batch25 current-source attach
  behavior.
- This slice only adds connection schema-cache generation/staleness diagnostics
  around successful ATTACH/DETACH lifecycle changes.

Dependency closure:

No new support component is needed. The slice reuses the existing
`SQLiteAttachedSchemaCatalog`, schema record, PRAGMA catalog, URI, and open-plan
primitives.
