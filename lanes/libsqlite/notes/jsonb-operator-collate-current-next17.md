# JSON Operator COLLATE RHS Current Next17

- Scope: parser-level SQLite JSON `->` and `->>` expression-index metadata when the RHS path constant is parenthesized and carries its own `COLLATE` clause.
- Upstream behavior: SQLite accepts expressions such as `json_col ->> ('cache' COLLATE nocase)`; the RHS collation belongs to the path expression and should not prevent normalizing the JSON path, while a following outer `COLLATE` remains the index term collation.
- Implementation: `SQLiteCreateIndex::readParenthesizedLiteral()` now accepts a trailing `COLLATE <name>` inside the parenthesized literal body before returning the underlying literal value.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonOperatorCollateCurrentTest.php` passes `1 test files, 32 assertions, 0 failures` with 30 PASS cases.
- Application smoke: `php lanes/libsqlite/examples/application-json-operator-collate-rhs.php` reports copied `wp_options` JSON text-operator expression indexes resolving collated RHS paths to native root pages and indexed option matches, plus value-operator metadata normalization evidence.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraints, SQL text SELECT/JOIN/GROUP/ORDER/subqueries, Unicode GLOB, VFS writer/lock/sync/rollback, WAL byte/checkpoint, B-tree page move/root/overflow, and previous JSON `json_quote()`/`min()`/`max()` RHS constants without collated parenthesized RHS handling.
- Dependency closure: no new support component is needed; this reuses the existing native SQLite schema/index parser and JSON path normalizer.
