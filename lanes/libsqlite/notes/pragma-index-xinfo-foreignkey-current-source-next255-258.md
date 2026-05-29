# PRAGMA index_xinfo foreign-key current-source next255-258

## Behavior

Adds real current-source pages after next254:

- next255 reports parent UNIQUE indexes whose `PRAGMA index_xinfo` collations
  differ from the parent table column declarations.
- next256 reports partial UNIQUE parent indexes, which cannot satisfy a foreign
  key parent key.
- next257 reports expression UNIQUE parent indexes as visible but unusable
  parent-key candidates.
- next258 reports descending UNIQUE parent keys from `PRAGMA index_xinfo`
  without treating descending order alone as a blocker.

Each page composes the previous accepted page, preserves cursor validation and
pagination, and records the new row summaries in current/next source hashes.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext255258Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next255-258.php --self-test`

## Non-Overlap

This is limited to PRAGMA index_xinfo / foreign-key current-source pages
next255 through next258 plus focused tests, example, and this note. It avoids
status, progress, dashboard, lane-status, supervisor, private files, and
unrelated rowvalue, pager, trigger, planner, WAL/VFS, B-tree, encoding, JSON,
and suite-runner clusters.
