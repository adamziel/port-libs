# encoding-utf16-nocase-like-rtrim-current-source-next193

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ? LIMIT ? OFFSET ?` current-source scans.

Application path: `application-utf16-nocase-like-rtrim-current-source-next193.php` models copied `wp_options.option_name` rows where an import cursor pages through plugin cache keys with `LIMIT/OFFSET`. When a next source inserts a matching UTF-16 row before the current window, the plan recomputes the offset window instead of continuing from stale rowids.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext193Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - `65` focused PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next193.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next193 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext193Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next193.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard movement: focused test adds `+65` PASS lines, moving `phpPass` from `92140` to `92205` if accepted. Mapped upstream coverage remains `617 / 1589`; this reuses existing encoding/collation/LIKE/RTRIM current-source inventory rather than claiming a fresh upstream manifest row.

Non-overlap: avoids accepted batch176 UTF-16 NOCASE/LIKE/RTRIM behavior, accepted next189 peer-window rowid tie-breakers, deleted-token resume, escaped residual token, case-sensitive LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards, and VFS/WAL/B-tree/JSON/SQL planner clusters. The new surface is LIMIT/OFFSET window replay safety after UTF-16 RTRIM/NOCASE LIKE rowsets change before or inside the current page.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE prefix ranges, RTRIM expression keys, current-source invalidation, and adds LIMIT/OFFSET replay diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, malformed-text, or LIKE/GLOB edge with focused tests, or pivot to a larger current-source closure bucket.
