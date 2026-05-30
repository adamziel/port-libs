# SQLite PDO polyfill first slice

Adds `PortLibs\LibSqlite\SQLitePDO extends \PDO` and `SQLitePDOStatement extends \PDOStatement` without calling native `pdo_sqlite` in production code.

Supported subset:

- DSNs: `sqlite::memory:` and empty/non-existent `sqlite:/path/to/file.sqlite`.
- Connection methods: `query()`, `prepare()`, `exec()`, `lastInsertId()`, `beginTransaction()`, `commit()`, `rollBack()`.
- Statement methods: `execute()`, `fetch()`, `fetchAll()`, `fetchColumn()`, `rowCount()`, `bindValue()`, `bindParam()`, and `setFetchMode()`.
- Fetch modes: `PDO::FETCH_ASSOC`, `PDO::FETCH_NUM`, `PDO::FETCH_BOTH`, and `PDO::FETCH_COLUMN`.
- SQL subset: simple `CREATE TABLE`, `INSERT INTO ... VALUES`, `SELECT`/`VALUES`/`WITH` through the existing libsqlite select executor, plus simple `UPDATE ... SET ... WHERE ...` and `DELETE FROM ... WHERE ...`.
- Parameters: positional `?` and named `:name` scalar bindings.

Unsupported native PDO features raise `PDOException`, including existing on-disk SQLite image loading, prepare options, nested transactions, non-forward cursors, rich fetch modes, attributes, error-code surfaces, BLOB streams, scrollable cursors, native SQLite pragmas, and full SQLite DDL/DML syntax.

PHP internal constraint investigated: userland subclasses can extend `\PDO` and `\PDOStatement` and override their constructors without invoking the internal native driver constructor. Method signatures must remain compatible with PHP's tentative native signatures.

Test evidence:

- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/src/SQLitePDOStatement.php`
- `php -l lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
