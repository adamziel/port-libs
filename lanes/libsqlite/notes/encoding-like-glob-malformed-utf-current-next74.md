# encoding-like-glob-malformed-utf-current-next74

- Behavior: extends `SQLiteGlobCursor` with malformed UTF-8 diagnostics for the pattern, current row, next row, and matched rows while keeping SQLite-style byte-prefix GLOB range scans over copied `wp_options.option_name` values.
- WordPress smoke: `lanes/libsqlite/examples/wordpress-option-name-glob-malformed-utf-cursor.php` reports damaged option-name prefix scans and Unicode class scans without requiring `ext/sqlite`.
- Non-overlap: avoids accepted Unicode GLOB range matching, accepted LIKE current/next ranges, malformed text comparison cursor current-next70, GLOB collation cursor current-next72, UTF-16 record encoding guards, and current VFS/WAL/B-tree/JSON/SQL executor clusters. This slice only adds malformed UTF-8 visibility at the GLOB cursor boundary.
- Dependency closure: no new support component is needed; this reuses native PHP GLOB matching and byte-oriented prefix bounds already present in `SQLiteDatabase`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteGlobMalformedUtfCursorTest.php
```
