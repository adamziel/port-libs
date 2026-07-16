# ATTACH Temp WAL Trigger View Cache Current Source Next97

## Behavior

This slice adds `SQLiteAttachTempWalSchemaTriggerPlan::triggerViewCacheCurrentSourceNext97()` for prepared `INSTEAD OF` triggers whose target is a view. It compares the current view cache snapshot with the next schema source after temp DDL, ATTACH/WAL schema-cookie changes, or attached-database view changes.

The planner now reports:

- active triggers that may finish on the current view source and return `SQLITE_OK` until reset;
- inactive triggers that must return `SQLITE_SCHEMA` before the next view-trigger step;
- temp view shadowing of an unqualified main view trigger;
- view SQL/dependency changes even when trigger SQL and output columns remain otherwise stable;
- WAL page-one schema-cookie sources that make the next trigger/view cache stale.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerViewCacheCurrentSourceNext97Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
50 PASS lines
```

Application smoke:

```bash
php lanes/libsqlite/examples/application-attach-temp-wal-trigger-view-cache-current-source-next97.php --self-test
```

## Non-Overlap

This does not repeat accepted ATTACH WAL/temp transaction routing, schema-cache statement expiry, trigger-cache current-source expiry, or batch88/batch90 trigger routing. The new behavior is specifically view-cache invalidation for prepared triggers targeting views when temp view shadowing, view SQL changes, attached view changes, and WAL schema-cookie sources affect the next trigger step.

## Dependency Closure

No new support component is needed. The slice reuses the existing attached schema catalog, schema records, and trigger current-source planner.
