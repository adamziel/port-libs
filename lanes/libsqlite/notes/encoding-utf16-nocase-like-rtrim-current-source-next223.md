# encoding-utf16-nocase-like-rtrim-current-source-next223

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source scans when a prepared cursor yields a descending `ORDER BY rtrim(option_name) COLLATE NOCASE DESC, rowid DESC LIMIT/OFFSET` page.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next223.php` models copied `wp_options.option_name` prefix scans where a plugin import inserts a higher descending key between yield points. The old current-source page token is no longer reusable because rows before the DESC LIMIT window and the page rowset changed after residual LIKE matching.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext223Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next223.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext223Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next223.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused `phpPass +75` from the new test file. Mapped upstream coverage remains `624 / 1589`; this is current-source PHP behavior over already mapped UTF-16, RTRIM, NOCASE, LIKE, and yield cursor inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next218 ascending LIMIT/OFFSET yield-page fencing, next208 prepared ESCAPE byte decoding, next200 ESCAPE rebinding, next211/next209 source-refresh and ASCII-only RTRIM/NOCASE diagnostics, Unicode GLOB ranges, malformed UTF-16 insert guards, and VFS/WAL/B-tree/JSON/SQL executor clusters. The new surface is the descending order page token and stale-page fence for UTF-16 RTRIM/NOCASE LIKE current-source cursors.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source yield cursor diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
