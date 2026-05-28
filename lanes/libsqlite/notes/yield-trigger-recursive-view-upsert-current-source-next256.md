# trigger-recursive-view-upsert-current-source-next256

Status: focused PHP behavior growth for recursive INSTEAD OF view trigger
UPSERT current-source fencing.

This slice adds
`SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Plan`. It extends the
accepted next253 recursive view UPSERT materialization receipt fence with
ordered batch-level current-source handoff receipts. The next source stays held
until every current materialized recursive view-trigger UPSERT row is covered by
the expected handoff token, projection hashes, rowid-provenance receipts, and
ordered acknowledgements.

WordPress path:
`wordpress-trigger-recursive-view-upsert-current-source-next256.php` models a
copied `wp_options` recursive import view where current child rows must finish
their view-trigger handoff before a later import source can publish `home` and
`next_plugin`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next256.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Test.php
php lanes/libsqlite/examples/wordpress-trigger-recursive-view-upsert-current-source-next256.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 68 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `136435 -> 136503` from 68 verified focused PASS lines in this
  isolated worktree.
- Mapped upstream coverage unchanged; this is focused PHP current-source
  behavior over an already mapped trigger/view/UPSERT family, not a fresh
  hydrated Tcl inventory row.

Non-overlap:

This slice avoids accepted next253 materialized projection receipts, next250
rowid provenance, next247 statement sequence, recursive view RETURNING-only,
row-value/window RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding,
PRAGMA, and suite evidence clusters. The new behavior is ordered batch-level
handoff receipt fencing after recursive view UPSERT materialization.

Dependency closure:

No new support component is needed. The slice reuses native recursive
view-trigger UPSERT planning, RETURNING payloads, rowid provenance, and
materialized current-source view receipts.
