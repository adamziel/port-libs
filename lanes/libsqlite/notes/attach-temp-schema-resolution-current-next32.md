# ATTACH temp schema resolution current/next32

## Scope

- Adds `SQLiteAttachTempSchemaResolutionPlan`, a bounded transition tracer for
  ATTACH/DETACH statements that snapshots current and next name resolution.
- Covers table, index, trigger, schema-table alias, schema PRAGMA, and trigger
  yield probes before and after each transition.
- Application smoke:
  `lanes/libsqlite/examples/application-attach-temp-schema-resolution-current-next32.php`
  shows copied `wp_options` TEMP shadowing while an attached site database is
  added and then detached.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempSchemaResolutionCurrentNext32Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-attach-temp-schema-resolution-current-next32.php
{
    "scenario": "application attach temp schema resolution current next32",
    "steps": [
        {
            "label": "attach-site",
            "operation": "attach",
            "afterSearchOrder": ["temp", "main", "site"],
            "unqualifiedWpOptionsSchema": "temp",
            "siteTriggerStatus": "yielded",
            "tempYieldWrites": {"temp": 1}
        },
        {
            "label": "detach-site",
            "operation": "detach",
            "afterSearchOrder": ["temp", "main"],
            "unqualifiedWpOptionsSchema": "temp",
            "siteTriggerStatus": "missing",
            "tempYieldWrites": {"temp": 1}
        }
    ]
}
```

## Non-Overlap

This avoids accepted batch27 temp view/trigger yield behavior by not adding new
trigger body statement semantics. It instead traces current/next ATTACH and
DETACH catalog transitions and verifies that the existing resolver continues
to pin TEMP, main, attached schemas, schema-table aliases, PRAGMAs, and yielded
trigger operations across schema changes.

## Dependency Closure

No new support component is needed. The slice reuses the native attached schema
catalog, schema PRAGMA catalog, trigger resolver, and bounded trigger-yield
plan.
