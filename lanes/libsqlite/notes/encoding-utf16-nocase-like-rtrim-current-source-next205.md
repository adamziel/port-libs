# encoding-utf16-nocase-like-rtrim-current-source-next205

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` scans when the fixed LIKE prefix contains non-ASCII text.

Application path: `application-utf16-nocase-like-rtrim-current-source-next205.php` models copied `wp_options.option_name` rows whose plugin prefix contains `ü`. SQLite's default NOCASE only folds ASCII, so a NOCASE LIKE range over a non-ASCII prefix is not safe. The current/next cursor must suppress indexed range reuse and perform a full residual scan after UTF-16 decoding and RTRIM expression-key evaluation.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext205Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next205.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext205Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next205.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +63`, from `98594` to `98657`. Mapped upstream coverage remains `620 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE, LIKE, RTRIM, and current-source inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next200 ESCAPE rebind, next195 escaped literal-tail residual transitions, next194 escaped wildcard prefix diagnostics, Unicode GLOB ranges, malformed UTF-16 insert guards, storage/VFS/WAL/B-tree/planner clusters, and suite-runner evidence work. The new surface is the non-ASCII NOCASE LIKE prefix fallback that suppresses range cursor reuse and requires full residual scanning.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode helpers, LIKE prefix planning, RTRIM expression keys, ASCII-only NOCASE residual matching, and current-source invalidation diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
