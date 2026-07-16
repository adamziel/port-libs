# yield-sqlite-application-json-schema-wal-current-next43

## Slice

Adds `SQLiteJsonSchemaWalPlan`, a bounded Application import planner that:

- plans copied `wp_options` schema DDL with schema/data cookie movement;
- validates configured JSON option rows before WAL append;
- rejects malformed JSON option rows with exact admission reasons;
- appends only accepted rows through the existing WAL current/next import planner;
- reports current versus next reader page sources and WAL frame indexes.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonSchemaWalCurrentNext43Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/application-json-schema-wal-current-next43.php --self-test
```

Output:

```json
{
    "status": "planned",
    "schema_version_after": 45,
    "accepted_import_count": 1,
    "rejected_rows": [
        {
            "option_name": "broken_plugin_json",
            "reason": "malformed_json_option_value",
            "error": "Syntax error"
        }
    ],
    "inserted_names": [
        "plugin_json_settings"
    ],
    "wal_last_commit_frame": 4
}
```

## Non-overlap

This does not repeat accepted JSON table cursor/source/hidden/visible constraint
work, WAL savepoint byte truncation, WAL checkpoint transaction planning, VFS
file writer/sync/lock application, rollback-journal commit/apply, or B-tree
page-move/freelist clusters. The new behavior is the Application import admission
boundary that combines schema metadata, JSON option validation, and WAL
current/next visibility in one bounded planner.

## Dependency closure

No new support component is needed. The slice reuses existing
`SQLiteSchemaBulkImportPlan`, `SQLiteOptionRowsWalImportPlan`,
and WAL parsing/append primitives.
