# pragma-rootpage-foreignkey-pointermap-current-source-next127

This slice tightens `SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext`
for attached-schema WordPress copy/import diagnostics. The helper now resolves
child and parent rootpage analysis rows by the catalog rootpage first, falling
back to table name only when a rootpage row is unavailable. That prevents a
duplicate `wp_options` or `wp_option_names` table name in an attached archive
schema from contaminating `main` foreign-key diagnostics, and still reports the
attached archive rootpage pointer-map conflict when the qualified
`pragma_foreign_key_check('archive.wp_options')` path is used.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaRootpageForeignKeyPointerMapCurrentSourceNext127Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 61 assertions, 0 failures`
  - `54` PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-rootpage-foreignkey-pointermap-current-source-next127.php`
  - printed `main_child_rootpage_status: ok` for rootpage `4` and
    `archive_child_rootpage_status: pointer_map` for rootpage `7`.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP schema catalog, PRAGMA foreign-key integrity, rootpage
integrity analysis, table leaf assembly, record encoding, and pointer-map
primitives.

Non-overlap: avoids accepted PRAGMA FK/rootpage pointer-map next122 coverage by
fixing duplicate-name rootpage resolution across attached schemas, rather than
adding another generic FK/rootpage diagnostic. It also avoids accepted PRAGMA
index/rootpage next124/next125, B-tree pointer-map mutation, VFS/WAL, JSON,
planner, trigger, and encoding surfaces.
