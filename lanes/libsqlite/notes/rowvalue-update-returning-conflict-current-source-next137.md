# rowvalue-update-returning-conflict-current-source-next137

Adds a bounded current-source plan for row-value `UPDATE ... RETURNING`
conflicts where `OR REPLACE` can delete a later selected row before that row
is admitted to the mutation/RETURNING stream.

The slice covers:

- selected row ids stay visible for diagnostics even when a later selected row
  is deleted by an earlier row-value conflict;
- `OR REPLACE` suppresses RETURNING for deleted later selected rows while
  still returning independent changed rows;
- `OR IGNORE` restores the conflicting row and lets the later selected peer
  run normally;
- composite unique keys such as `(blog_id, option_name)` drive conflict
  detection, matching Application multisite option imports.

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateReturningConflictCurrentSourceNext137Test.php
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-rowvalue-update-returning-conflict-current-source-next137.php
```

Expected dashboard movement: update `phpPass` by the focused PASS-line delta
for this new test file: `+57`, from `58373` to `58430`. Mapped coverage is
unchanged; this is current-source PHP behavior over already mapped
row-value/UPDATE/RETURNING inventory.

Verified result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

Dependency closure: no new support component is needed. This reuses the
native PHP UPDATE RETURNING row-value assignment executor and unique-conflict
handling.

Non-overlap: avoids accepted next130 row-value UPDATE OR REPLACE/IGNORE
current-source conflict rows by focusing on selected-row admission when an
earlier replacement deletes a later selected row before RETURNING. It also
avoids next132 FAIL/savepoint preservation, next133 row-value `IS`/`IS NOT`,
next134 row-value UPSERT conflict policy, and all WAL/VFS/B-tree/JSON/schema
clusters.
