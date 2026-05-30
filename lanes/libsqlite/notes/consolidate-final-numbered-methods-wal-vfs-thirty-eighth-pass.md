# WAL/VFS numbered method consolidation thirty-eighth pass

Consolidated the VFS current-source handle-reuse production helper names in
`SQLiteVfsCurrentSourceNextPlan`. The slice dispatch still accepts the existing
`next146-149` behavior label for direct test/example compatibility, but the
production implementation no longer exposes the numbered `run146149()` and
private `*146149()` helper names for that behavior.

No compatibility shim was left for the removed numbered production method
names. The exact user-named 150 suffix remains absent from production source.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext146149Test.php lanes/libsqlite/tests/SQLiteVfsCloseReopenCurrentSourceTest.php`
- `php lanes/libsqlite/examples/application-vfs-current-source-next146-149.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This is a production
helper-name consolidation over existing lane-local VFS current-source behavior.

Non-overlap: this pass only renames VFS current-source handle-reuse production
helpers. It does not change WAL/pager durability behavior, B-tree, JSON table,
planner, PRAGMA, trigger, upstream-suite evidence, dashboard files, or root
coordination files.
