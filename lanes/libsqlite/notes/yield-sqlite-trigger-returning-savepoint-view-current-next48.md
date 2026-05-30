# SQLite view trigger RETURNING savepoint current-next48

## Delta

- Added `SQLiteViewTriggerReturningSavepointPlan` for bounded `INSTEAD OF` view-trigger execution under a current savepoint.
- The planner composes existing attached-schema trigger-yield resolution with savepoint page/WAL rollback diagnostics and SQLite-style `RETURNING` current-row images.
- Added a Application smoke for copied `wp_options` view insert behavior.
- Updated `lane-status.json` `phpPass` from `17373` to `17436` for the 63 newly verified focused PASS lines.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteViewTriggerReturningSavepointCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-view-trigger-returning-savepoint.php
{
    "returning": [
        {
            "option_id": 2,
            "option_name": "home",
            "value": "https://new.test"
        }
    ],
    "changes": 2,
    "writesBySchema": {
        "main": 2
    },
    "optionNames": [
        "siteurl",
        "home"
    ],
    "auditLabels": [
        "view-insert"
    ]
}
```

## Non-overlap

This does not repeat standalone attached temp view trigger yielding, trigger/FK `RETURNING`, recursive savepoint trigger rollback, savepoint page-image rollback, VFS savepoint rollback apply, or parser-level JSON/SELECT source work. The new surface is the composition of `INSTEAD OF` view-trigger side effects, current-row `RETURNING`, and current-savepoint rollback restoration for Application-style option imports.

## Dependency closure

No new support component is needed. The slice reuses existing bounded native PHP schema catalog, trigger-yield, and savepoint stack components.
