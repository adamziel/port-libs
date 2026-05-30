# trigger-recursive-view-upsert-current-source-next250

Status: focused PHP behavior growth for recursive INSTEAD OF view-trigger
UPSERT current-source fencing.

This slice adds `SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan`. It
extends accepted next247 statement-source sequence admission with current
rowid-provenance receipts for recursive UPSERT rows. The next source remains
held until every current recursive UPSERT RETURNING row has a stable rowid
provenance receipt tied to the current source token, conflict key, rowid
column, and next247 statement sequence receipt.

Application path:
`application-trigger-recursive-view-upsert-current-source-next250.php` models a
copied `wp_options` recursive import view where current rows spawn recursive
child UPSERT RETURNING rows, then a staged next import must wait until current
rowid provenance is acknowledged.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next250.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext250Test.php
php lanes/libsqlite/examples/application-trigger-recursive-view-upsert-current-source-next250.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 81 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `129612 -> 129693` from 81 verified focused PASS lines in this
  isolated worktree.
- Mapped upstream coverage unchanged; this is focused PHP current-source
  behavior over the already mapped trigger/view/UPSERT family, not a fresh
  hydrated Tcl inventory row.

Non-overlap:

This slice avoids accepted next247 statement sequence receipts, next246
conflict-image receipts, next244 commit watermark behavior, source-cookie
fences, recursive view RETURNING cursor/ticket/generation surfaces, row-value
RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding,
and B-tree clusters. The new behavior is current UPSERT rowid-provenance
receipt fencing after statement-sequence admission.

Dependency closure:

No new support component is needed. The slice reuses native recursive
view-trigger UPSERT planning, RETURNING payloads, current-source commit
watermarks, and statement sequence receipts.
