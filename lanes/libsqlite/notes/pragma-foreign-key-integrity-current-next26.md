# PRAGMA Foreign-Key Integrity Current Next26

This slice adds a bounded schema-current `PRAGMA foreign_key_check` integrity
executor for Application import/staging diagnostics.

Behavior covered:

- Unqualified `PRAGMA foreign_key_check(table)` resolves the current child
  table through `temp`, `main`, then attached schemas before checking rows.
- Explicit schema pins such as `main.foreign_key_check(...)`,
  `temp.foreign_key_check(...)`, and `archive.foreign_key_check(...)` bypass
  shadowing and check only that schema.
- Returned violation rows include the schema that supplied the child table,
  preserving SQLite-style `table`, `rowid`, `parent`, and `fkid` fields.
- All-schema integrity collection preserves `temp` before `main`, then
  attached schema order for migration preflight summaries.
- The implementation reuses the accepted direct
  `SQLitePragmaForeignKeyCheck` affinity, collation, composite-key, NULL-key,
  and WITHOUT ROWID behavior rather than duplicating that checker.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityCurrentNext26Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-foreign-key-integrity-current-next26.php
{
    "scenario": "copied wp_options foreign-key integrity preflight",
    "current_target_schema": "temp",
    "current_target_rows": [
        {
            "schema": "temp",
            "table": "wp_options",
            "rowid": 21,
            "parent": "wp_option_names",
            "fkid": 0
        }
    ],
    "main_pinned_rows": [
        {
            "schema": "main",
            "table": "wp_options",
            "rowid": 11,
            "parent": "wp_sites",
            "fkid": 0
        }
    ],
    "archive_rows": [
        {
            "schema": "archive",
            "table": "wp_options_archive",
            "rowid": 31,
            "parent": "wp_blogs",
            "fkid": 0
        }
    ],
    "dependency": "native PHP schema-current PRAGMA foreign_key_check planner; no ext/sqlite required"
}
```

Non-overlap: avoids accepted `PRAGMA foreign_key_list` direct/table-valued
metadata, the direct `foreign_key_check` affinity/collation corpus,
`PRAGMA integrity_check`/`quick_check`, PRAGMA metadata lists, VFS
writer/sync/rollback, WAL checkpoint/savepoint byte work, JSON table
source/cursor/constraint clusters, Unicode GLOB, B-tree page
move/root-collapse/overflow freelist, and batch23 PRAGMA metadata work.

Dependency closure: no new support component is needed. The slice reuses
`SQLiteAttachedSchemaCatalog` for current-source resolution and
`SQLitePragmaForeignKeyCheck` for FK comparison semantics.
