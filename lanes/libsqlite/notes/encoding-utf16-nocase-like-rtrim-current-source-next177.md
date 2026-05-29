# encoding-utf16-nocase-like-rtrim-current-source-next177

Status: focused PHP behavior growth for UTF-16 decoded LIKE `_` wildcard semantics over `rtrim(option_name) COLLATE NOCASE` current-source cursors.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next177.php` models copied `wp_options.option_name` rows where plugin cache keys are stored in mixed UTF-16LE/UTF-16BE and include non-ASCII single-character suffixes. The plan keeps the accepted NOCASE/RTRIM prefix range, then proves the residual LIKE `_` wildcard consumes one decoded SQLite text character, including UTF-16 surrogate pairs, rather than one byte or one UTF-16 code unit.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next177.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Test.php`
  - `1 test files, 81 assertions, 0 failures`
  - `68` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next177.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next177 self-test passed`

Expected dashboard movement: `phpPass +68`, from `82455` to `82523`. Mapped upstream coverage remains `613 / 1589`; this is current-source behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE wildcard, and cursor invalidation inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next168 `case_sensitive_like` toggling, next171 duplicate-key replay, next172 yield-token duplicate prevention, next173 byte-vs-semantic invalidation, next174 embedded-NUL full-text recheck, UTF-16 malformed insert guards, Unicode GLOB ranges, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite-runner evidence work. The new surface is specifically LIKE `_` character wildcard behavior after UTF-16 decoding under a NOCASE/RTRIM current-source range.

Dependency closure: no new support component needed; reuses native UTF-16 decode, LIKE character matching, NOCASE/RTRIM prefix planning, and adds Unicode wildcard recheck diagnostics for current-source cursor transitions.
