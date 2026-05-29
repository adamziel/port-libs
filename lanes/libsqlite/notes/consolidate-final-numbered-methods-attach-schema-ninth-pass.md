## Attach/Schema Numbered Method Consolidation Ninth Pass

Consolidated the remaining attach/schema numbered production entrypoints in:

- `SQLiteAttachTempWalSchemaTriggerPlan`
- `SQLiteAttachWalTempSchemaCookieSourcePlan`
- `SQLiteAttachSchemaCookieRepreparePlan`
- `SQLiteAttachWalTempViewCachePlan`

The direct tests now call descriptive unsuffixed methods such as
`triggerSourceRepreparePlan()`, `triggerBodyDependencyRepreparePlan()`,
`schemaCookieRepreparePlan()`, `sharedCacheRepreparePlan()`,
`bracketQuotedSchemaCookieSourcePlan()`, `cteSchemaCookieSourcePlan()`, and
`preparedViewCacheRepreparePlan()`. The related operation/dependency labels,
including the base schema-cookie source and trigger-view cache labels, were
also renamed away from the worker-numbered suffixes.

Verification:

- `php -l` passed for the 4 changed production PHP files and 16 changed focused
  attach/schema test files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCookieReprepareCurrentSourceNext100Test.php lanes/libsqlite/tests/SQLiteAttachTempSchemaCacheReprepareCurrentSourceNext103Test.php lanes/libsqlite/tests/SQLiteAttachTempSchemaTriggerCacheCurrentSourceNext85Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieCurrentSourceNext94Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieCurrentSourceNext99Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerCurrentSourceNext90Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerCurrentSourceNext95Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerReprepareCurrentSourceNext104Test.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheCurrentSourceNext93Test.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaViewCacheCurrentSourceNext82Test.php lanes/libsqlite/tests/SQLiteAttachWalTempTriggerCacheCurrentSourceNext89Test.php lanes/libsqlite/tests/SQLiteAttachTempMainWalViewCacheCurrentNext78Test.php`
  plus the direct base label callers
  `SQLiteAttachTempWalSchemaCacheCurrentSourceNext96Test.php`,
  `SQLiteAttachTempTriggerViewInvalidationCurrentSourceNextTest.php`, and
  `SQLiteAttachWalTempSchemaCookieCurrentSourceNext87Test.php` passed:
  `16 test files, 1590 assertions, 0 failures`.
- Targeted attach/schema numbered method scan is clean for the migrated
  suffixes.

Dependency closure: no new support component is needed; this is a production
method-name consolidation only.
