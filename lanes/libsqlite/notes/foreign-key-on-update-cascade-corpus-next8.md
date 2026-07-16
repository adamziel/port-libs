# Foreign Key ON UPDATE Cascade Corpus Next8

This slice adds a bounded upstream-style `ON UPDATE` foreign-key action corpus
without repeating the accepted deferred `ON DELETE` cascade corpus. The new
`SQLiteForeignKeyOnUpdateCascadePlan` models parent-key UPDATE statements,
deferred action records, commit-time child-key rewrites, `SET NULL`,
`SET DEFAULT`, `NO ACTION`, `RESTRICT`, rollback preview, and malformed input
guards over copied row arrays.

The Application smoke uses a copied option-group migration shape: renumbering an
option group cascades the `group_id` stored on related option rows without
requiring `ext/sqlite`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCascadeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS foreign key on update cascade rewrites parent key
...
PASS foreign key on update rejects missing child column

1 test files, 50 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-foreign-key-on-update-cascade.php
{
    "groups": [
        10,
        2
    ],
    "option_group_ids": [
        10,
        10,
        2
    ],
    "deferred_actions": 1,
    "commit_actions": [
        "cascade-update-child",
        "cascade-update-child"
    ],
    "changes": 3
}
```

Non-overlap: this is `ON UPDATE` parent-key mutation behavior. It avoids the
accepted deferred `ON DELETE` cascade corpus, trigger-conflict inheritance,
WAL/VFS/B-tree/JSON/SELECT executor clusters, and storage diagnostics.

Dependency closure: no new support component is needed; the slice reuses the
existing row-array PHP execution model and test harness.
