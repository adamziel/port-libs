# trigger-recursive-view-upsert-current-source-next247

Status: focused PHP behavior growth for recursive INSTEAD OF view trigger
UPSERT current-source fencing.

This slice adds
`SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan`. It extends the
accepted next244 statement commit-watermark behavior with statement-source
sequence admission. The next source remains held until the current recursive
view-trigger UPSERT has acknowledged all current RETURNING rows against the
current statement sequence and the next source advertises a future sequence.

WordPress path:
`wordpress-trigger-recursive-view-upsert-current-source-next247.php` models a
copied `wp_options` recursive import view where current `siteurl` and plugin
UPSERT rows spawn recursive child RETURNING rows, then a later import source
must not publish until the current source sequence is complete.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next247.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext247Test.php
php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next247.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 83 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `126252 -> 126335` from 83 verified focused PASS lines in this
  isolated worktree.
- Mapped upstream coverage unchanged; this is focused PHP current-source
  behavior over an already mapped trigger/view/UPSERT family, not a fresh
  hydrated Tcl inventory row.

Non-overlap:

This slice avoids accepted next244 commit receipt/watermark behavior, next242
statement epoch fencing, next239 target receipts, recursive view RETURNING
cursor/ticket/generation surfaces, row-value RETURNING savepoints, schema
reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters. The new
behavior is statement-source sequence fencing after current recursive
view-trigger UPSERT commit admission.

Dependency closure:

No new support component is needed. The slice reuses native recursive
view-trigger UPSERT planning, RETURNING payloads, and current-source commit
watermark receipts.
