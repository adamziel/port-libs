# JSON table LEFT JOIN rowid current next76

## Behavior

Adds focused regression coverage for parser-level `json_each()` / `json_tree()`
LEFT JOIN sources where the virtual table rowid aliases are used in join and
post-join predicates.

The covered Application-shaped path is copied `wp_options` plugin settings:
dynamic JSON roots from host columns, JSONB option values, SQL NULL option
values, empty arrays, missing roots, and `json_tree()` object fields all
preserve SQLite-style `rowid`, `_rowid_`, and `oid` behavior while unmatched
LEFT JOIN rows are NULL-extended.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLeftJoinRowidCurrentNext76Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 51 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-json-table-left-join-rowid-current-next76.php
[
    {
        "option_name": "plugin_empty_settings",
        "json_rowid": null,
        "json__rowid_": null,
        "json_oid": null,
        "flag": null
    },
    {
        "option_name": "plugin_media_settings",
        "json_rowid": 2,
        "json__rowid_": 2,
        "json_oid": 2,
        "flag": "forms"
    },
    {
        "option_name": "plugin_route_settings",
        "json_rowid": 2,
        "json__rowid_": 2,
        "json_oid": 2,
        "flag": "beta"
    }
]
```

## Non-overlap

This does not repeat accepted JSON table cursor/source wiring, hidden
constraint extraction, visible constraint pushdown, host joins, NULL-path,
LIMIT/OFFSET, window ranking, aggregate/window, or malformed JSONB admission
clusters. It is a rowid-alias LEFT JOIN regression slice on top of those
accepted surfaces.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteJsonTablePlan`, `SQLiteJsonEach`,
`SQLiteJsonTree`, and JSONB helpers.
