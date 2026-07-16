# encoding-collation-utf16-glob-current-source-next122

Status: focused PHP behavior growth for UTF-16 GLOB current-source literal bracket ranges.

This slice fixes `SQLiteDatabase::globPrefixRangeBounds()` so unmatched `[` is treated like SQLite's GLOB matcher treats it: a literal byte in the fixed prefix, not an automatic character-class opener. Valid bracket classes still stop prefix planning at the class boundary.

Application path: `application-utf16-glob-literal-bracket-current-source-next122.php` models copied `wp_options` plugin keys containing literal bracket markers such as `plugin_[draft]`, where a prepared UTF-16 GLOB scan must keep a narrow range across a current/next source transition and report range-byte invalidation when the cursor encoding changes.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteUtf16GlobLiteralBracketCurrentSourceNext122Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16GlobLiteralBracketCurrentSourceNext122Test.php`
  - `1 test files, 51 assertions, 0 failures`
- `php -l lanes/libsqlite/examples/application-utf16-glob-literal-bracket-current-source-next122.php`
- `php lanes/libsqlite/examples/application-utf16-glob-literal-bracket-current-source-next122.php --self-test`
  - `application-utf16-glob-literal-bracket-current-source-next122 self-test passed`

PASS delta: `+51` focused assertions. `lane-status.json` `phpPass` moves from `47656` to `47707`. Mapped upstream coverage remains `604 / 1589`; this reuses already mapped GLOB, UTF-16 range encoding, and current-source invalidation inventory.

Non-overlap: avoids accepted Unicode GLOB range matching, malformed UTF-16 guards, RTRIM/NOCASE GLOB current-source next119 behavior, UTF-16 collation/affinity pattern current-source next118 behavior, JSON/VFS/WAL/B-tree/SQL executor current-source clusters, and release-runner evidence work. The new surface is literal unmatched bracket prefix extraction for UTF-16 GLOB current/next range scans.

Dependency closure: no new support component is needed. The patch reuses native PHP GLOB matching, UTF-16 encode/decode helpers, and existing current-source range diagnostics.

Next task: continue encoding work only on a non-overlapping malformed-text or collation/affinity predicate edge with focused tests.
