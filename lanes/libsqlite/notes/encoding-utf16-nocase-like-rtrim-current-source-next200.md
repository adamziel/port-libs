# encoding-utf16-nocase-like-rtrim-current-source-next200

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source scans when the prepared `ESCAPE` binding changes before a next-source cursor reuse.

Application path: `application-utf16-nocase-like-rtrim-current-source-next200.php` models copied `wp_options.option_name` prefix scans where a plugin import changes the escape character from `!` to `~`. The same SQL pattern text can move the prefix range from literal `plugin_` keys to literal `plugin!` keys, so the cursor must reprepare before reusing the old range or residual rowset.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next200.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next200.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next200 self-test passed`

Expected dashboard movement: `phpPass +76`, from `95731` to `95807`. Mapped upstream coverage remains `619 / 1589`; this is current-source PHP behavior over already mapped UTF-16, RTRIM, NOCASE, LIKE, and ESCAPE inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next194 escaped wildcard literal-prefix diagnostics, next188/next185 deleted-token and rowid replay, next181 peer replay, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters. The new surface is `ESCAPE` rebinding as a source-transition fence before UTF-16 RTRIM/NOCASE LIKE range cursor reuse.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode, LIKE ESCAPE prefix planning, RTRIM expression keys, NOCASE residual matching, and current-source invalidation diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
