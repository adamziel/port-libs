# PRAGMA index_xinfo foreign-key current-source next252-255

## Behavior

Adds a consolidated handoff over accepted PRAGMA index_xinfo / foreign-key
current-source slices next252 through next255:

- next252 reports foreign-key child columns that are absent even from
  `PRAGMA table_xinfo`.
- next253 reports generated child columns that would be unsafe targets for
  `SET NULL` or `SET DEFAULT` actions.
- next254 reports nullable parent columns behind UNIQUE parent keys discovered
  through `PRAGMA index_xinfo`.
- next255 is now provided by the PRAGMA index_xinfo/FK source and reports
  parent UNIQUE index collation mismatches using `PRAGMA index_xinfo`.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext252255Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next252-255.php --self-test`

## Non-Overlap

This verifies the accepted next252 missing-child-column, next253
generated-child action, next254 nullable parent-key behavior, and next255
parent-key collation availability as a single current-source handoff. It avoids
planner, WAL/VFS, B-tree, encoding, JSON, trigger, rowvalue, pager,
suite-runner, and unrelated PRAGMA integrity/rootpage clusters.

## Dependency Closure

No new support component is needed. The handoff reuses the existing schema
catalog, `PRAGMA foreign_key_list`, `PRAGMA index_list`, `PRAGMA index_xinfo`,
`PRAGMA table_info`, and `PRAGMA table_xinfo` extraction paths.
