# Schema Foreign Key Single-Quoted Current

- Slice: `libsqlite-schema-foreign-key-current`.
- Behavior: `SQLitePragmaSchemaCatalog` now reads single-quoted SQLite identifiers while extracting `PRAGMA foreign_key_list` rows from `sqlite_schema` `CREATE TABLE` SQL.
- Upstream basis: SQLite accepts single-quoted identifiers in schema SQL in compatibility cases; `PRAGMA foreign_key_list` reports the unquoted child table, child columns, parent table, parent columns, actions, and match clauses.
- WordPress path: copied legacy export/import schemas can preserve FK diagnostics for option metadata tables whose identifiers were dumped with single quotes.
- Non-overlap: does not touch numbered current-source helpers, WAL/VFS/B-tree clusters, JSON table planners, expression `ORDER BY`, or accepted FK action/index-xinfo diagnostic families.
- Dependency closure: no new support component is needed; this reuses the existing schema catalog parser and PRAGMA row cursor.

Verification to record after patch:

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php lanes/libsqlite/tests/SQLiteSchemaForeignKeySingleQuotedCurrentTest.php lanes/libsqlite/examples/wordpress-schema-foreign-key-single-quoted-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaForeignKeySingleQuotedCurrentTest.php`
- `php lanes/libsqlite/examples/wordpress-schema-foreign-key-single-quoted-current.php --self-test`
- `git diff --check -- lanes/libsqlite`
