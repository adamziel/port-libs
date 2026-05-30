# SQLite PDO polyfill first slice

Adds `PortLibs\LibSqlite\SQLitePDO extends \PDO` and `SQLitePDOStatement extends \PDOStatement` without calling native `pdo_sqlite` in production code.

Supported subset:

- DSNs: `sqlite::memory:` and empty/non-existent `sqlite:/path/to/file.sqlite`.
- Connection methods: `query()`, `prepare()`, `exec()`, `lastInsertId()`, `beginTransaction()`, `commit()`, `rollBack()`.
- Connection attributes: `PDO::ATTR_ERRMODE` (`PDO::ERRMODE_EXCEPTION` only), `PDO::ATTR_DEFAULT_FETCH_MODE`, and `PDO::ATTR_DRIVER_NAME`.
- Statement methods: `execute()`, `fetch()`, `fetchAll()`, `fetchColumn()`, `rowCount()`, `columnCount()`, `bindValue()`, `bindParam()`, `bindColumn()`, and `setFetchMode()`.
- Fetch modes: `PDO::FETCH_ASSOC`, `PDO::FETCH_NUM`, `PDO::FETCH_BOTH`, `PDO::FETCH_COLUMN` including column indexes passed through `query()`, `PDO::FETCH_OBJ`, `PDO::FETCH_BOUND`, `PDO::FETCH_CLASS`, and `PDO::FETCH_INTO`.
- SQL subset: simple `CREATE TABLE`, `INSERT INTO ... VALUES`, `SELECT`/`VALUES`/`WITH` through the existing libsqlite select executor, plus simple `UPDATE ... SET ... WHERE ...` and `DELETE FROM ... WHERE ...`.
- Parameters: positional `?` and named `:name` scalar bindings.

Unsupported native PDO features raise `PDOException`, including existing on-disk SQLite image loading, prepare options, nested transactions, non-forward cursors, unsupported attributes, BLOB streams, scrollable cursors, native SQLite pragmas, and full SQLite DDL/DML syntax.

PHP internal constraint investigated: userland subclasses can extend `\PDO` and `\PDOStatement` and override their constructors without invoking the internal native driver constructor. Method signatures must remain compatible with PHP's tentative native signatures.

Test evidence:

- `php -l lanes/libsqlite/src/SQLitePDO.php`
- `php -l lanes/libsqlite/src/SQLitePDOStatement.php`
- `php -l lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePdoPolyfillTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Native comparison coverage:

- `PDO::getAvailableDrivers()` included `sqlite` in the local PHP environment used for this slice.
- `SQLitePdoPolyfillTest.php` compares the polyfill with native `pdo_sqlite` for `sqlite::memory:`, `exec()`, named and positional prepared parameters, `query()`, fetched rows, and transaction rollback. The comparison case returns early when the driver is unavailable.
