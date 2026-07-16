# PRAGMA Foreign Key List Current Next18

This slice adds the SQLite table-valued PRAGMA function form for
`foreign_key_list` beside the existing direct `PRAGMA foreign_key_list(table)`
catalog executor.

Behavior covered:

- `pragma_foreign_key_list(table)` follows current-source resolution
  (`temp`, `main`, then attached schemas), matching the direct PRAGMA path.
- `pragma_foreign_key_list(table, schema)` pins lookup to a requested schema
  and raises for unattached schema names.
- Quoted table names, quoted schema names, bare identifiers, trailing
  semicolons, and case-insensitive function names are accepted.
- Row output reuses the existing upstream-shaped columns:
  `id`, `seq`, `table`, `from`, `to`, `on_update`, `on_delete`, and `match`.
- Composite foreign keys preserve shared `id` values and per-column `seq`.
- References without an explicit parent column report `to = null`, matching
  SQLite PRAGMA row shape.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php`
- `php lanes/libsqlite/examples/application-pragma-foreign-key-list-current-next18.php`
- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
- `php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-foreign-key-list-current-next18.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The implementation
reuses the existing schema catalog parser, attached-schema catalog, and
`SQLitePragmaRowCursor`.

Non-overlap: this does not repeat direct `PRAGMA foreign_key_list(...)`
current-source rows from current-next16; it adds the table-valued
`pragma_foreign_key_list(...)` call form used by upstream SQLite's PRAGMA
virtual-table interface.
