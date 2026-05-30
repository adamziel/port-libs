# Attach/schema suffix consolidation, fifty-first pass

- Renamed attach/schema trigger cache helpers from generated suffix wording to
  stable descriptive names:
  `triggerViewCacheRepreparePlan()`,
  `triggerViewDependencyInvalidationPlan()`, and
  `triggerProgramCacheRepreparePlan()`.
- Migrated the direct tests and Application examples to non-suffixed filenames
  and call sites. No numbered compatibility shims were added.
- Focused verification:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheReprepareTest.php lanes/libsqlite/tests/SQLiteAttachTempTriggerViewInvalidationTest.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerCacheReprepareTest.php`
  passed with `3 test files, 214 assertions, 0 failures`.
- Example self-tests passed for the three renamed Application attach/schema
  trigger cache examples.
- Dependency closure: no new support component needed; this reuses the existing
  attached schema catalog, trigger cache, view cache, and WAL schema-cookie
  helpers.
