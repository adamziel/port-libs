# Foreign-key cascade update trigger current next29

Status: focused current-source PHP test growth for cascaded child-table UPDATE
trigger behavior.

This slice adds `SQLiteForeignKeyCascadeUpdateTriggerPlan`, a bounded row-array
model for SQLite-style `ON UPDATE CASCADE` where child rows updated by the
foreign-key action fire child-table UPDATE triggers and may cascade into a
grandchild table. It covers BEFORE/AFTER child trigger ordering, OLD/NEW child
visibility, trigger audit rows, trigger rewrites/deletes of grandchild rows
before or after the FK action, no-op parent updates, `NO ACTION`, multi-parent
updates, missing-column guards, and unsupported-action guards.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyCascadeUpdateTriggerCurrentNext29Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-foreign-key-cascade-update-trigger-current-next29.php
```

Non-overlap: this does not repeat accepted deferred parent-key cascades,
parent-side trigger/FK interaction, FK `ON UPDATE` cascade corpus coverage,
UPSERT trigger/FK yield behavior, recursive trigger depth, or current-next19
`ON DELETE CASCADE` child-trigger behavior. It focuses on child UPDATE triggers
caused by FK-cascaded parent key updates and optional grandchild `ON UPDATE`
cascade current/next row visibility.

Dependency closure: no new support component is needed; the slice reuses the
existing lane-local row-array FK and trigger modeling style.
