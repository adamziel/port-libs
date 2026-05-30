# Attach/schema numbered marker consolidation, twenty-eighth pass

## Scope

Consolidated three remaining attach/schema production operation/dependency marker names that encoded worker numbers:

- `attach-schema-cookie-reprepare-current-source-next84` -> `attach-schema-cookie-reprepare-current-source`
- `attach-schema-cache-ddl-current-source-next107` -> `attach-schema-cache-ddl-current-source`
- `attach-temp-trigger-view-invalidation-current-source-next108` -> `attach-temp-trigger-view-invalidation-current-source`

Direct tests and Application example self-tests were migrated to the stable marker names. No production numbered class/file/helper shim was added.

## Verification

- `php -l` on all changed PHP files: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCacheDdlCurrentSourceNext107Test.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext84Test.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCacheReprepareCurrentSourceNext103Test.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext100Test.php lanes/libsqlite/tests/SQLiteAttachTempTriggerViewInvalidationCurrentSourceNextTest.php`: `5 test files, 532 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-attach-schema-cache-ddl-current-source-next107.php --self-test`: pass
- `php lanes/libsqlite/examples/application-attach-temp-trigger-view-invalidation-current-source-next.php --self-test`: pass
- `git diff --check -- lanes/libsqlite`: pass

## Dependency closure

No new support component is needed. This is a production marker-name consolidation only; it preserves the existing attach/schema behavior and focused assertions.
