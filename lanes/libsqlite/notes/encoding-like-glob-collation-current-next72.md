# encoding-like-glob-collation-current-next72

- Behavior: adds a bounded `SQLiteGlobCursor` for SQLite-style GLOB index scans over copied `wp_options.option_name` rows. It keeps current/next cursor diagnostics, BINARY/NOCASE/RTRIM index ordering, byte-prefix range bounds, and residual GLOB matching separate so NOCASE index traversal does not make GLOB case-insensitive.
- WordPress smoke: `lanes/libsqlite/examples/wordpress-option-name-glob-cursor.php` reports plugin option-name prefix and Unicode class GLOB rowids without requiring `ext/sqlite`.
- Non-overlap: avoids accepted LIKE current/next cursor ranges, accepted Unicode GLOB matcher/range semantics, batch65 LIKE SQL semantics, malformed UTF-16 record encoding guards, JSON table cursor/source work, and recent VFS/WAL/B-tree apply clusters.
- Dependency closure: no new support component is needed; this reuses existing native PHP `SQLiteDatabase::globMatches()` and `globPrefixRangeBounds()` behavior.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGlobCursorCurrentNext72Test.php`
  - `php lanes/libsqlite/examples/wordpress-option-name-glob-cursor.php`
