# yield-sqlite-jsonb-table-generated-cascade-current-next53

## Behavior

- Adds `SQLiteJsonbGeneratedCascadePlan` for generated parent keys derived from JSONB values with `jsonb_extract()`.
- Covers `ON UPDATE CASCADE`, `ON DELETE CASCADE`, `ON DELETE SET NULL`, `ON DELETE SET DEFAULT`, no-action violation reporting, text-JSON source promotion back to JSONB, malformed path/identifier guards, and missing-column validation.
- Adds a Application multisite import smoke where copied `wp_options` JSONB site identifiers drive generated parent keys and `wp_optionmeta`-style rows cascade after site-key rewrites/deletes.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbTableGeneratedCascadeCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-jsonb-table-generated-cascade-current-next53.php
{
    "changes": 5,
    "after_parent_keys": [
        "site-1-imported"
    ],
    "after_child_keys": [
        "site-1-imported",
        "site-1-imported"
    ],
    "actions": [
        "update-parent-generated-jsonb",
        "cascade-update-child-generated-key",
        "cascade-update-child-generated-key",
        "delete-parent-generated-jsonb",
        "cascade-delete-child-generated-key"
    ],
    "violations": []
}
```

## Non-Overlap

This does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSONB generated partial UPSERT index maintenance, generic FK cascade trigger tests, VFS writer/sync/rollback work, SQL expression ORDER BY/subquery/GROUP BY text dispatch, or B-tree page-move/freeblock/overflow release clusters. The new surface is JSONB-generated parent-key cascade application.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSONB extraction/mutation and row-array FK planning primitives.
