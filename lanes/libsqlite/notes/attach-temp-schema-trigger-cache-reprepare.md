# Attach Temp Schema Trigger Cache Reprepare

## Behavior

Adds bounded current-source trigger-cache planning for prepared trigger programs
after temp/main/attached schema rewrites. The planner records the trigger
definition, resolved target table/view, OLD/NEW references, and trigger-body
dependencies before and after schema-record replacement.

SQLite-compatible current-source outcomes are modeled explicitly:

- active changed trigger programs finish the current step from the old source
  and report `SQLITE_SCHEMA` on reset;
- inactive changed trigger programs report `SQLITE_SCHEMA` on the next step;
- qualified main triggers are not expired by temp trigger shadow rewrites;
- unrelated attached-schema rewrites do not expire main trigger programs;
- committed WAL page-one schema cookies are counted as next-source cookies,
  while uncommitted or non-page-one frames are ignored.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempSchemaTriggerCacheReprepareTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 66 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-attach-temp-schema-trigger-cache-reprepare.php --self-test
```

## Non-Overlap

This does not repeat accepted ATTACH WAL/temp rollback routing, temp WAL schema
cache, view-cache reprepare, or view/trigger route plans. The new
surface is prepared trigger-program cache expiry and current-source reset/next
step behavior after schema-record replacement.

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLiteAttachedSchemaCatalog`, `SQLiteSchemaRecord`, and trigger-resolution
helpers.
