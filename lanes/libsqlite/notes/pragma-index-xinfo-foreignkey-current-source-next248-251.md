# PRAGMA index_xinfo foreign-key current-source next248-251

## Behavior

Adds a consolidated handoff over accepted PRAGMA index_xinfo / foreign-key
current-source slices next248 through next251:

- next248 keeps parent-key UNIQUE indexes that were created outside the
  table definition visible as drop-sensitive FK mismatch risks.
- next249 and next250 keep generated child columns visible through
  `PRAGMA table_xinfo` when `PRAGMA table_info` would omit them.
- next251 rejects child-side action lookup indexes whose prefix includes an
  expression key term exposed by `PRAGMA index_xinfo`.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext248251Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next248-251.php --self-test`

## Non-Overlap

This does not add a new source primitive. It verifies the already accepted
next248 external parent UNIQUE origin, next249/next250 generated child-column
visibility and repair, and next251 expression child action-index blocker as a
single current-source handoff. It avoids planner, WAL/VFS, B-tree, encoding,
JSON, trigger, suite-runner, and unrelated PRAGMA integrity/rootpage clusters.

## Dependency Closure

No new support component is needed. The handoff reuses the existing schema
catalog, `PRAGMA foreign_key_list`, `PRAGMA index_list`, `PRAGMA index_xinfo`,
and `PRAGMA table_xinfo` extraction paths.
