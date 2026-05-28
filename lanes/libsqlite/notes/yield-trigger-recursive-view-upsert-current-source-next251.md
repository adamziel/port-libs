# trigger-recursive-view-upsert-current-source-next251

Status: focused PHP behavior growth for recursive INSTEAD OF view trigger
UPSERT current-source fencing.

This slice adds
`SQLiteTriggerRecursiveViewUpsertCurrentSourceNext251Plan`. It extends the
accepted next247 statement-source sequence behavior with current-source
change-counter admission. The next source remains held until the current
recursive view-trigger UPSERT has acknowledged all current RETURNING rows
against deterministic statement `changes()` and monotonic `total_changes()`
style receipts.

WordPress path:
`wordpress-trigger-recursive-view-upsert-current-source-next251.php` models a
copied `wp_options` recursive import view where current `siteurl` and plugin
UPSERT rows spawn recursive child RETURNING rows, then a later import source
must not publish until the current source change counters are complete.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext251Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext251Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next251.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext251Test.php
php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next251.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 88 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `129612 -> 129700` from 88 verified focused PASS lines in this
  isolated worktree.
- Mapped upstream coverage unchanged; this is focused PHP current-source
  behavior over an already mapped trigger/view/UPSERT family, not a fresh
  hydrated Tcl inventory row.

Non-overlap:

This slice avoids accepted next247 statement sequence receipts, next244 commit
watermarks, next239 target receipts, recursive view RETURNING
cursor/ticket/generation surfaces, WAL/VFS, JSON table, planner, encoding, and
B-tree clusters. The new behavior is change-counter receipt fencing after
current recursive view-trigger UPSERT statement sequence admission.

Dependency closure:

No new support component is needed. The slice reuses native recursive
view-trigger UPSERT planning, RETURNING payloads, current-source commit
watermarks, and statement-source sequence receipts.
