# Foreign Key Deferred Cascade Corpus

This slice adds a bounded upstream-style deferred foreign-key parent DELETE
corpus without repeating the accepted trigger-conflict or generic foreign-key
action markers. The new `SQLiteForeignKeyDeferredCascadePlan` models deferred
commit-time `ON DELETE` behavior for copied row arrays:

- `CASCADE` deletes matching child rows at deferred commit.
- `SET NULL` and `SET DEFAULT` rewrite matching child keys.
- `NO ACTION` preserves children and reports commit-time violations.
- `RESTRICT` raises before deferred commit, matching SQLite's early timing.
- Rollback preview restores parent/child images and clears the deferred queue.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS foreign key deferred cascade corpus deletes parent at statement boundary
...
PASS foreign key deferred cascade rejects missing child column

1 test files, 38 assertions, 0 failures

php lanes/libsqlite/examples/application-foreign-key-deferred-cascade.php
{
    "remaining_groups": [
        "manual"
    ],
    "remaining_options": [
        "blogname"
    ],
    "deferred_actions": 1,
    "commit_actions": [
        "cascade-delete-child",
        "cascade-delete-child"
    ],
    "changes": 3
}
```

Dependency closure: no new support component is needed. The slice reuses the
existing row-array executor style and adds one lane-local planner for deferred
foreign-key commit behavior.
